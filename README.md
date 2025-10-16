# Ayosms PHP SDK v1.0

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)
[![Build](https://img.shields.io/badge/build-passing-success.svg)](#)
[![Packagist](https://img.shields.io/badge/Packagist-imamrasyid%2Fayosms--sdk--php-orange.svg)](#)
[![CodeIgniter Compatible](https://img.shields.io/badge/CodeIgniter-3%2F4-red.svg)](#)

A modern, production-ready **PHP SDK** for [AYOSMS! Global SMS Gateway](https://ayosms.com/api/), fully aligned with their official API documentation.

> ⚙️ Designed for developers who want reliable SMS, OTP, and HLR integration within PHP applications — including CodeIgniter, Laravel, or pure PHP environments.

---

## 🚀 Features

✅ **Complete API Coverage**

- `sendSMS()` — Send single or bulk SMS
- `checkBalance()` — Retrieve account balance
- `sendHLR()` — Perform HLR lookups
- `otpRequest()` — Request OTP delivery via SMS
- `otpCheck()` — Verify received OTP

✅ **Robust Error Handling**

- Handles network, HTTP, and JSON parsing errors
- Detects invalid parameters and malformed requests
- Maps AYOSMS official error codes (`ERR001–ERR999`)

✅ **Professional PHPDoc**

- Full type-hinting for modern IDEs (PhpStorm, VSCode, etc.)
- Inline documentation for every parameter and return type

✅ **Secure & Standards-Compliant**

- Uses HTTPS with `CURLOPT_SSL_VERIFYPEER`
- Sanitizes and validates all inputs before request
- Compatible with PHP 7.4 – 8.3

✅ **CodeIgniter Ready**

- Drop directly into `application/libraries/Ayosms.php`
- Optional config file `application/config/ayosms.php`

---

## 🧠 Installation

### 🧩 Manual Install

```bash
# Copy the SDK file
cp Ayosms.php /path/to/your/project/application/libraries/
```

### ⚙️ Composer (recommended for modular use)

If you’re managing dependencies manually:

```bash
composer require imamrasyid/php-ayosms
```

_(You can create your own package from this repo if you wish to publish it on Packagist.)_

---

## 🧰 Configuration

```php
// application/config/ayosms.php
$config['api_key'] = 'YOUR_AYOSMS_API_KEY';
```

Then in your controller:

```php
$this->load->library('Ayosms', $this->config->item('ayosms'));
```

Or in pure PHP:

```php
require_once 'Ayosms.php';
$ayosms = new Ayosms(['api_key' => 'YOUR_API_KEY']);
```

---

## ✉️ Usage Examples

### 1️⃣ Send SMS

```php
$response = $ayosms->sendSMS(
    from: 'AYOSMS',
    to: ['628123456789', '628987654321'],
    msg: 'Hello from AYOSMS PHP SDK!',
    options: [
        'trx_id' => 'demo-001',
        'dlr' => 1,
        'priority' => 'high'
    ]
);

echo $response; // JSON output
```

### 2️⃣ Check Balance

```php
$response = $ayosms->checkBalance();
```

### 3️⃣ HLR Lookup

```php
$response = $ayosms->sendHLR('628123456789', [
    'trx_id' => 'hlr-check-1'
]);
```

### 4️⃣ OTP Request

```php
$response = $ayosms->otpRequest([
    'from' => 'AYOSMS',
    'to' => '628123456789',
    'secret' => 'my-shared-secret',
    'msisdncheck' => 1
]);
```

### 5️⃣ OTP Check

```php
$response = $ayosms->otpCheck([
    'from' => 'AYOSMS',
    'secret' => 'my-shared-secret',
    'pin' => '123456'
]);
```

---

## 🧾 Response Format

Every method returns a **JSON string** — you can decode it via:

```php
$data = json_decode($response, true);
if ($data['status'] === 1) {
    echo 'Success!';
} else {
    echo 'Error: ' . $data['error-text'];
}
```

---

## ⚡ Error Reference (Common Codes)

| Code      | Description                           |
| --------- | ------------------------------------- |
| `ERR001`  | Account suspended                     |
| `ERR002`  | Insufficient balance                  |
| `ERR005`  | Invalid or empty `from`               |
| `ERR006`  | Invalid or empty `to`                 |
| `ERR007`  | Empty or too long message             |
| `ERR008`  | Invalid characters (non-GSM 7-bit)    |
| `ERR010`  | Invalid datetime format               |
| `ERR011`  | Delivery time is in the past          |
| `ERR012`  | Secret (OTP) missing                  |
| `ERR013`  | trx_id too long                       |
| `ERR999`  | API Key missing                       |
| `CURL001` | Network/connection error              |
| `HTTP###` | Unexpected HTTP code (e.g., 404, 500) |
| `JSON###` | Response not valid JSON               |

---

## 🧪 DLR (Delivery Report) Integration

If you enable DLR (Delivery Report) callback in your AYOSMS dashboard, use the helper:

```php
$result = $ayosms->validateDlrPayload($_POST);
if ($result['valid']) {
    echo 'OK'; // required by AYOSMS
} else {
    error_log('Invalid DLR: ' . implode(',', $result['errors']));
}
```

---

## 🧩 Contributing

Pull requests are welcome! Please follow:

- PSR-12 coding standard
- PHPDoc best practices
- Commit message style: `feat:`, `fix:`, `docs:` etc.

---

## 🪄 Example Integration (CodeIgniter 3)

```php
class Sms extends CI_Controller {
    public function send() {
        $this->load->library('Ayosms', ['api_key' => 'YOUR_KEY']);
        $resp = $this->ayosms->sendSMS('AYOSMS', '628123456789', 'Testing AYOSMS SDK');
        echo $resp;
    }
}
```

---

## 🧭 License

MIT License © 2025 — Created by [Imam Rasyid](https://github.com/dev_eyetracker)

> “Simple, elegant, and reliable — just like your SMS delivery.” 📡
