# Laravel Cloudflare Mail

A Laravel mail transport driver for [Cloudflare Email Service](https://developers.cloudflare.com/email-service/).

> **Note:** Cloudflare Email Service is currently in beta. Ensure your account has access before using this package.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require tomahock/laravel-cloudflare-mail
```

Publish the config file:

```bash
php artisan vendor:publish --tag=cloudflare-mail-config
```

## Configuration

Add the following variables to your `.env` file:

```env
CLOUDFLARE_ACCOUNT_ID=your-account-id
CLOUDFLARE_API_TOKEN=your-api-token
```

Configure the mailer in `config/mail.php`:

```php
'mailers' => [
    'cloudflare' => [
        'transport' => 'cloudflare',
    ],
],
```

Set it as the default mailer:

```env
MAIL_MAILER=cloudflare
```

## Usage

Use Laravel's standard `Mail` facade — no API-specific code required:

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;

Mail::to('user@example.com')->send(new WelcomeMail());
```

Or with a raw message:

```php
Mail::raw('Hello World', function ($message) {
    $message->to('recipient@example.com')
            ->from('sender@yourdomain.com', 'Your App')
            ->subject('Hello');
});
```

## Creating API Tokens

1. Go to [Cloudflare Dashboard](https://dash.cloudflare.com/) → **My Profile** → **API Tokens**
2. Create a token with **Email Service: Send** permission scoped to your account
3. Copy the token into `CLOUDFLARE_API_TOKEN`

Your `CLOUDFLARE_ACCOUNT_ID` is visible in the URL when logged into the Cloudflare dashboard (`dash.cloudflare.com/{account_id}/...`) or under **Account Home → Overview**.

## Supported Features

| Feature | Supported |
|---------|-----------|
| Plain text body | ✅ |
| HTML body | ✅ |
| CC / BCC | ✅ |
| Reply-To | ✅ |
| Named addresses (`"Name" <email>`) | ✅ |
| File attachments | ✅ |
| Multiple recipients | ✅ |

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
