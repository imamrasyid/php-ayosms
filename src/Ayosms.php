<?php

namespace ImamRasyid\Ayosms;

// defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Ayosms PHP SDK
 * ------------------------------------------------------------
 * A production-grade SDK for AYOSMS! HTTP APIs.
 *
 * Features implemented (per official docs):
 * - Send SMS API             : https://api.ayosms.com/mconnect/gw/sendsms.php
 * - Check Balance API        : https://api.ayosms.com/mconnect/gw/balance.php
 * - Send HLR API             : https://api.ayosms.com/mconnect/gw/sendhlr.php
 * - OTP Verify Request API   : https://api.ayosms.com/mconnect/gw/verifyrequest.php
 * - OTP Verify Check API     : https://api.ayosms.com/mconnect/gw/verifycheck.php
 *
 * Notes from spec:
 * - Supports HTTP GET & POST; this SDK uses POST by default (application/x-www-form-urlencoded, UTF-8).
 * - SMS supports GSM 7-bit characters only, max 400 chars; multi-destination via comma.
 * - Delivery time is GMT+7; format differs by API (see each method's PHPDoc).
 * - Delivery Report (DLR) callback requires your endpoint to respond with plain text "OK".
 *
 * @package   Application\Libraries
 * @author    Your Name
 * @version   1.0
 * @license   MIT
 */
class Ayosms
{
    /**
     * @var string AYOSMS! API key (Account Management > My Account)
     */
    private $apiKey;

    /** @var string Send SMS endpoint */
    private $smsEndpoint = 'https://api.ayosms.com/mconnect/gw/sendsms.php';

    /** @var string Check Balance endpoint */
    private $balanceEndpoint = 'https://api.ayosms.com/mconnect/gw/balance.php';

    /** @var string Send HLR endpoint */
    private $hlrEndpoint = 'https://api.ayosms.com/mconnect/gw/sendhlr.php';

    /** @var string OTP Verify Request endpoint */
    private $otpRequestEndpoint = 'https://api.ayosms.com/mconnect/gw/verifyrequest.php';

    /** @var string OTP Verify Check endpoint */
    private $otpCheckEndpoint = 'https://api.ayosms.com/mconnect/gw/verifycheck.php';

    /**
     * Construct the SDK.
     *
     * @param array{api_key?:string} $config Configuration array, expects 'api_key'.
     *
     * @example
     * $this->load->library('Ayosms', ['api_key' => 'YOUR_API_KEY']);
     */
    public function __construct($config = [])
    {
        $this->apiKey = isset($config['api_key']) ? trim($config['api_key']) : '';
    }

    // =====================================================================
    // Public APIs
    // =====================================================================

    /**
     * Send SMS (single or multiple recipients).
     *
     * Delivery time format per docs: yyyymmddMMii (GMT+7) where MM=hour, ii=minute.
     * In PHP date() terms, use 'YmdHi'.
     *
     * @param string               $from          Sender ID (URL-encoded by SDK). Max 11 chars recommended.
     * @param string|string[]      $to            Destination MSISDN(s) in international format without '+'. Accept comma-separated or array.
     * @param string               $msg           Message body (GSM 7-bit only, max 400 chars).
     * @param array{
     *   trx_id?:string,
     *   dlr?:int,
     *   delivery_time?:string,   // Input as 'Y-m-d H:i:s' (local time); SDK converts to 'YmdHi' GMT+7.
     *   priority?:string         // e.g. 'high' for OTP speed
     * }                          $options       Optional parameters.
     *
     * @return string JSON string from AYOSMS! with added 'segment' on success; standardized error JSON on failure.
     */
    public function sendSMS($from, $to, $msg, array $options = [])
    {
        try {
            // Basic validations
            if (empty($this->apiKey)) return $this->error('ERR999', 'api_key is empty');
            if (empty($from) || strlen($from) > 11) return $this->error('ERR005', 'from error or empty');
            if (empty($to)) return $this->error('ERR006', 'to error or empty');
            if (empty($msg) || strlen($msg) > 400) return $this->error('ERR007', 'msg error or empty');
            if (!$this->isGSM7Bit($msg)) return $this->error('ERR008', 'msg contains non-GSM 7-bit characters');

            $toList = $this->normalizeDestinations($to);
            if ($toList === false) return $this->error('ERR006', 'to format invalid');

            $trx_id        = isset($options['trx_id']) ? substr($options['trx_id'], 0, 36) : '';
            $dlr           = isset($options['dlr']) ? (int)$options['dlr'] : 0; // default 0 unless requested
            $priority      = isset($options['priority']) ? $options['priority'] : '';
            $deliveryInput = isset($options['delivery_time']) ? trim($options['delivery_time']) : '';

            $delivery_time = '';
            if ($deliveryInput !== '') {
                $delivery_time = $this->toAyosmsDatetime($deliveryInput, 'YmdHi'); // GMT+7
                if ($this->isErrorJson($delivery_time)) return $delivery_time;
            }

            $params = [
                'api_key'       => $this->apiKey,
                'from'          => rawurlencode(substr($from, 0, 11)), // docs show URL-encoded sender
                'to'            => implode(',', $toList),
                'msg'           => rawurlencode($msg),
                'trx_id'        => $trx_id,
                'dlr'           => $dlr,
            ];
            if ($delivery_time !== '') $params['delivery_time'] = $delivery_time;
            if ($priority !== '')      $params['priority']      = $priority; // "high" for OTP speed

            $json = $this->httpPost($this->smsEndpoint, $params);
            if ($this->isErrorJson($json)) return $json;

            $resp = json_decode($json, true);
            if (!is_array($resp)) return $this->error('JSON001', 'invalid JSON response');
            if (!array_key_exists('status', $resp)) return $this->error('API001', 'response missing status');
            if ((int)$resp['status'] === 0) return $this->error('AYOERR', isset($resp['error-text']) ? $resp['error-text'] : 'unknown api error');

            // Add local info: segments
            $resp['segment'] = $this->calcSegments($msg);
            return json_encode($resp);
        } catch (\Throwable $e) {
            return $this->error('EXC001', 'Exception: ' . $e->getMessage());
        }
    }

    /**
     * Check remaining balance.
     *
     * @return string JSON string with {status, balance, currency, balance_expired} or standardized error JSON.
     */
    public function checkBalance()
    {
        try {
            if (empty($this->apiKey)) return $this->error('ERR999', 'api_key is empty');

            $params = ['api_key' => $this->apiKey];
            $json   = $this->httpPost($this->balanceEndpoint, $params);
            if ($this->isErrorJson($json)) return $json;

            $resp = json_decode($json, true);
            if (!is_array($resp)) return $this->error('JSON002', 'invalid JSON response');
            if (!array_key_exists('status', $resp)) return $this->error('API002', 'response missing status');
            if ((int)$resp['status'] === 0) return $this->error('AYOERR', isset($resp['error-text']) ? $resp['error-text'] : 'unknown balance error');

            return json_encode([
                'status'          => 1,
                'balance'         => isset($resp['balance']) ? $resp['balance'] : '',
                'currency'        => isset($resp['currency']) ? $resp['currency'] : '',
                'balance_expired' => isset($resp['balance_expired']) ? $resp['balance_expired'] : '',
            ]);
        } catch (\Throwable $e) {
            return $this->error('EXC002', 'Exception: ' . $e->getMessage());
        }
    }

    /**
     * Send HLR lookup request.
     *
     * Delivery time format per HLR docs: yyyymmddMM (GMT+7) where MM=hour (no minutes). In PHP: 'YmdH'.
     *
     * @param string|string[] $to     Destination MSISDN(s) without '+'. Accept comma-separated or array.
     * @param array{
     *   trx_id?:string,
     *   delivery_time?:string        // Input as 'Y-m-d H:i:s' (local time); SDK converts to 'YmdH' GMT+7.
     * }               $options       Optional parameters.
     *
     * @return string JSON string from AYOSMS! or standardized error JSON.
     */
    public function sendHLR($to, array $options = [])
    {
        try {
            if (empty($this->apiKey)) return $this->error('ERR999', 'api_key is empty');
            if (empty($to)) return $this->error('ERR006', 'to error or empty');

            $toList = $this->normalizeDestinations($to);
            if ($toList === false) return $this->error('ERR006', 'to format invalid');

            $trx_id        = isset($options['trx_id']) ? substr($options['trx_id'], 0, 36) : '';
            $deliveryInput = isset($options['delivery_time']) ? trim($options['delivery_time']) : '';

            $delivery_time = '';
            if ($deliveryInput !== '') {
                $delivery_time = $this->toAyosmsDatetime($deliveryInput, 'YmdH'); // GMT+7 (hour precision)
                if ($this->isErrorJson($delivery_time)) return $delivery_time;
            }

            $params = [
                'api_key' => $this->apiKey,
                'to'      => implode(',', $toList),
            ];
            if ($trx_id !== '')       $params['trx_id']       = $trx_id;
            if ($delivery_time !== '') $params['delivery_time'] = $delivery_time;

            $json = $this->httpPost($this->hlrEndpoint, $params);
            if ($this->isErrorJson($json)) return $json;

            $resp = json_decode($json, true);
            if (!is_array($resp)) return $this->error('JSON003', 'invalid JSON response');
            if (!array_key_exists('status', $resp)) return $this->error('API003', 'response missing status');
            if ((int)$resp['status'] === 0) return $this->error('AYOERR', isset($resp['error-text']) ? $resp['error-text'] : 'unknown api error');

            return json_encode($resp);
        } catch (\Throwable $e) {
            return $this->error('EXC003', 'Exception: ' . $e->getMessage());
        }
    }

    /**
     * OTP Verify - Request a PIN to be sent via SMS.
     *
     * The official docs require api_key and typically include: from (sender), to (MSISDN), secret, and optional flags
     * like msisdncheck. This SDK accepts a flexible $params array to accommodate provider-side changes.
     *
     * @param array{
     *   from:string,                // Sender ID (URL-encoded by SDK)
     *   to:string,                  // Destination MSISDN (without '+')
     *   secret:string,              // Your shared secret
     *   msisdncheck?:int,           // 1 to enable msisdn matching
     *   pin_length?:int,            // optional - if supported by provider
     *   template?:string,           // optional - if supported by provider
     *   [string]:mixed              // any additional passthrough params
     * } $params
     *
     * @return string JSON string from AYOSMS! or standardized error JSON.
     */
    public function otpRequest(array $params)
    {
        try {
            if (empty($this->apiKey)) return $this->error('ERR999', 'api_key is empty');
            if (empty($params['from'])) return $this->error('ERR005', 'from error or empty');
            if (empty($params['to'])) return $this->error('ERR006', 'to error or empty');
            if (empty($params['secret'])) return $this->error('ERR012', 'secret error or empty');

            $toList = $this->normalizeDestinations($params['to']);
            if ($toList === false) return $this->error('ERR006', 'to format invalid');

            $payload = [
                'api_key' => $this->apiKey,
                'from'    => rawurlencode(substr($params['from'], 0, 11)),
                'to'      => implode(',', $toList),
                'secret'  => $params['secret'],
            ];

            // Optional passthroughs
            $passthroughKeys = ['msisdncheck', 'pin_length', 'template'];
            foreach ($params as $k => $v) {
                if (in_array($k, $passthroughKeys, true)) {
                    $payload[$k] = is_string($v) ? trim($v) : $v;
                }
            }

            $json = $this->httpPost($this->otpRequestEndpoint, $payload);
            if ($this->isErrorJson($json)) return $json;

            $resp = json_decode($json, true);
            if (!is_array($resp)) return $this->error('JSON004', 'invalid JSON response');
            if (!array_key_exists('status', $resp)) return $this->error('API004', 'response missing status');
            if ((int)$resp['status'] === 0) return $this->error('AYOERR', isset($resp['error-text']) ? $resp['error-text'] : 'unknown api error');

            return json_encode($resp);
        } catch (\Throwable $e) {
            return $this->error('EXC004', 'Exception: ' . $e->getMessage());
        }
    }

    /**
     * OTP Verify - Check the PIN provided by user.
     *
     * @param array{
     *   from:string,                // Sender ID used previously (URL-encoded by SDK)
     *   secret:string,              // Your shared secret
     *   pin:string,                 // PIN entered by user
     *   msisdncheck?:int            // 1 if you also want to validate MSISDN match
     * } $params
     *
     * @return string JSON string from AYOSMS! or standardized error JSON.
     */
    public function otpCheck(array $params)
    {
        try {
            if (empty($this->apiKey)) return $this->error('ERR999', 'api_key is empty');
            if (empty($params['from'])) return $this->error('ERR005', 'from error or empty');
            if (empty($params['secret'])) return $this->error('ERR012', 'secret error or empty');
            if (empty($params['pin'])) return $this->error('ERR014', 'pin error or empty');

            $payload = [
                'api_key' => $this->apiKey,
                'from'    => rawurlencode(substr($params['from'], 0, 11)),
                'secret'  => $params['secret'],
                'pin'     => $params['pin'],
            ];
            if (isset($params['msisdncheck'])) $payload['msisdncheck'] = (int)$params['msisdncheck'];

            $json = $this->httpPost($this->otpCheckEndpoint, $payload);
            if ($this->isErrorJson($json)) return $json;

            $resp = json_decode($json, true);
            if (!is_array($resp)) return $this->error('JSON005', 'invalid JSON response');
            if (!array_key_exists('status', $resp)) return $this->error('API005', 'response missing status');
            if ((int)$resp['status'] === 0) return $this->error('AYOERR', isset($resp['error-text']) ? $resp['error-text'] : 'unknown api error');

            return json_encode($resp);
        } catch (\Throwable $e) {
            return $this->error('EXC005', 'Exception: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Normalize destination(s) to 62XXXXXXXX format; returns array or false if invalid.
     *
     * @param string|string[] $to Comma-separated string or array of MSISDNs.
     * @return array|false
     */
    private function normalizeDestinations($to)
    {
        $list = is_array($to) ? $to : explode(',', $to);
        $out  = [];
        foreach ($list as $raw) {
            $msisdn = $this->formatMsisdn(trim((string)$raw));
            if (!preg_match('/^62\d{9,13}$/', $msisdn)) return false;
            $out[] = $msisdn;
        }
        return $out;
    }

    /**
     * Format a phone number to 62XXXXXXXX (strip non-digits, handle 0-prefix).
     *
     * @param string $number Raw number
     * @return string Normalized MSISDN
     */
    private function formatMsisdn($number)
    {
        $number = preg_replace('/[^0-9]/', '', (string)$number);
        if (strpos($number, '0') === 0) {
            return '62' . substr($number, 1);
        }
        if (strpos($number, '62') !== 0) {
            return '62' . $number;
        }
        return $number;
    }

    /**
     * Convert 'Y-m-d H:i:s' local time to AYOSMS format in GMT+7.
     *
     * @param string $inputDatetime  A local time string 'Y-m-d H:i:s'.
     * @param string $format         Output PHP date format (e.g., 'YmdHi' or 'YmdH').
     * @return string JSON error string on failure; otherwise formatted datetime string.
     */
    private function toAyosmsDatetime($inputDatetime, $format)
    {
        $ts = strtotime($inputDatetime);
        if ($ts === false) return $this->error('ERR010', 'invalid datetime format');
        if ($ts < time()) return $this->error('ERR011', 'delivery time is in the past');

        // Convert to GMT+7 for output (docs state GMT+7 reference)
        $dt = new \DateTime('@' . $ts); // UTC
        $dt->setTimezone(new \DateTimeZone('Asia/Jakarta'));
        return $dt->format($format);
    }

    /**
     * Calculate GSM 7-bit SMS segments (160 / 153 rule).
     *
     * @param string $msg Message body
     * @return int Number of segments
     */
    private function calcSegments($msg)
    {
        $len = strlen($msg);
        return ($len <= 160) ? 1 : (int)ceil(($len - 160) / 153) + 1;
    }

    /**
     * GSM 7-bit character validation per GSM 03.38 basic + extensions.
     *
     * @param string $text
     * @return bool
     */
    private function isGSM7Bit($text)
    {
        $gsm = '@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!" #¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€';
        $gsm .= "\n\r";
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            if (strpos($gsm, $text[$i]) === false) return false;
        }
        return true;
    }

    /**
     * Perform HTTP POST with cURL and return raw body, or standardized error JSON.
     *
     * @param string $url
     * @param array  $params
     * @return string
     */
    protected function httpPost(string $endpoint, array $params): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return json_encode([
                'status' => 0,
                'error-text' => 'CURL Error: ' . $error,
            ]);
        }

        curl_close($ch);
        return $response ?: json_encode([
            'status' => 0,
            'error-text' => 'Empty response from API',
        ]);
    }

    /**
     * Check whether a string is a standardized error JSON produced by this SDK.
     *
     * @param string $json
     * @return bool
     */
    private function isErrorJson($json)
    {
        if (!is_string($json)) return false;
        $arr = json_decode($json, true);
        return is_array($arr) && isset($arr['status']) && (int)$arr['status'] === 0 && isset($arr['error-text']);
    }

    /**
     * Build standardized error JSON.
     *
     * Known AYOSMS error codes include (non-exhaustive):
     * ERR001 account suspended, ERR002 insufficient balance, ERR005 from error or empty,
     * ERR006 to error or empty, ERR007 msg error or empty, ERR008 invalid char,
     * ERR009 invalid sender ID, ERR010 invalid datetime format, ERR011 delivery time past,
     * ERR012 secret error or empty, ERR013 trx_id too long, ERR999 api_key empty.
     *
     * @param string $code
     * @param string $message
     * @return string JSON string
     */
    private function error($code, $message)
    {
        return json_encode([
            'status'     => 0,
            'error-text' => $code . ': ' . $message,
            'timestamp'  => date('Y-m-d H:i:s'),
        ]);
    }

    // =====================================================================
    // (Optional) DLR helper
    // =====================================================================

    /**
     * Validate minimal required fields for a DLR callback payload from AYOSMS! (if you use DLR).
     *
     * @param array $payload Typically includes msg_id, trx_id, from, to, delivered (UNIX ts GMT+7), status (1/0), error-text, meta-data.
     * @return array{valid:bool,errors?:string[]} Simple validation result.
     */
    public function validateDlrPayload(array $payload)
    {
        $errors = [];
        foreach (['msg_id', 'to', 'status'] as $k) {
            if (!isset($payload[$k]) || $payload[$k] === '') $errors[] = "$k is missing";
        }
        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
}
