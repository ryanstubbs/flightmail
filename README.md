# FlightMail

[![CI](https://github.com/ryanstubbs/flightmail/actions/workflows/ci.yml/badge.svg)](https://github.com/ryanstubbs/flightmail/actions/workflows/ci.yml) [![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](composer.json) ![Packagist Version](https://img.shields.io/packagist/v/ryanstubbs/flightmail) ![Packagist Downloads](https://img.shields.io/packagist/dt/ryanstubbs/flightmail)

Send email from your [Flight PHP](https://flightphp.com) app without the headaches.

FlightMail is a small plugin that wraps **Symfony Mailer** - the most battle-tested mail library in PHP - and makes it feel like part of Flight. One line to install, one fluent chain to send:

```php
Flight::mail()->compose()
    ->to('someone@example.com')
    ->subject('You did it!')
    ->text('Your first email is on its way.')
    ->send();
```

**Why you'll like it:**

- **Any provider, one line each.** SMTP, Postmark, Sendgrid, Mailgun, Amazon SES, Brevo and friends all work through simple DSN strings.
- **Use several providers at once.** Transactional mail through Postmark, newsletters through your own SMTP - pick per message.
- **Templates if you want them.** Render bodies with Twig or Latte. Don't want templates? Just pass strings and install nothing extra.
- **Email-safe styling, optionally automatic.** Inline CSS into your HTML so Gmail doesn't strip it - one Composer package away.
- **Both parts, half the work.** Let FlightMail generate the plain-text part from your HTML (Markdown quality), instead of writing it twice.
- **Boring in the best way.** Lazy connections, clear errors instead of silently swallowed mail, and everything is swappable if you need something custom.

---

## Requirements

| What           | Version                                |
| -------------- | -------------------------------------- |
| PHP            | 8.2 or newer                           |
| Flight PHP     | core ^3.15                             |
| Symfony Mailer | ^7.2 or ^8.0 (installed automatically) |

## Installation

```bash
composer require ryanstubbs/flightmail
```

That's it for sending plain-text and HTML emails. Everything else is opt-in - add an engine only if you'll use it:

```bash
composer require twig/twig                 # for .twig templates
composer require latte/latte               # for .latte templates
composer require pelago/emogrifier         # for CSS inlining ("inline_css")
composer require league/html-to-markdown   # for Markdown text parts ("text_from_html")
```

All of these can be installed side by side; FlightMail picks the right one based on what you configure.

## Your first email

Add this to your bootstrap (the same place you define routes):

```php
<?php
require 'vendor/autoload.php';

use ryanstubbs\FlightMail\MailPlugin;

// Tell FlightMail where to send mail from and through.
MailPlugin::install([
    'dsns' => [
        'default' => 'smtp://user:pass@localhost:1025',
    ],
    'from' => 'no-reply@example.com',
]);

Flight::route('/signup', function () {
    Flight::mail()->compose()
        ->to('new-user@example.com')
        ->subject('Welcome aboard!')
        ->html('<h1>Welcome!</h1><p>We are glad you are here.</p>')
        ->send();
});

Flight::start();
```

Using the [Flight PHP skeleton](https://github.com/flightphp/skeleton) instead? Register in `app/config/services.php` with the instance style:

```php
use ryanstubbs\FlightMail\MailPlugin;

MailPlugin::register($app, [
    'dsns' => ['default' => 'smtp://user:pass@localhost:1025'],
    'from' => 'no-reply@example.com',
]);
```

Both styles expose the same mailer: `Flight::mail()` and `$app->mail()` are interchangeable.

> **Testing locally?** If your project runs in [DDEV](https://ddev.com), point the DSN at `smtp://127.0.0.1:1025` and read every captured email in Mailpit at `http://<project>.ddev.site:8025`. Nothing leaves your machine.

## Sending email

### Plain strings (no template engine needed)

`->text()` and `->html()` take raw strings and need nothing else installed:

```php
Flight::mail()->compose()
    ->to('ops@example.com')
    ->subject('Backup finished')
    ->text('Nightly backup completed in 42 minutes.')
    ->send();

Flight::mail()->compose()
    ->to('billing@example.com')
    ->subject('Invoice #123')
    ->html('<h1>Invoice #123</h1><p>Total due: $42.00</p>')
    ->send();
```

### Twig templates

```php
// welcome.html.twig contains: Hello {{ name }}, thanks for signing up!
Flight::mail()->compose()
    ->to('someone@example.com')
    ->subject('Welcome!')
    ->template('welcome.html.twig', ['name' => 'Ryan'])
    ->send();
```

### Latte templates

Same idea, `.latte` extension:

```php
// welcome.latte contains: Hello {$name}, thanks for signing up!
Flight::mail()->compose()
    ->to('someone@example.com')
    ->subject('Welcome!')
    ->template('welcome.latte', ['name' => 'Ryan'])
    ->send();
```

### HTML + plain text together

Best practice for deliverability - give mail clients both versions:

```php
Flight::mail()->compose()
    ->to('someone@example.com')
    ->subject('Welcome!')
    ->template('welcome.html.twig', ['name' => 'Ryan'])     // rich version
    ->textTemplate('welcome.txt.twig', ['name' => 'Ryan'])  // fallback version
    ->send();
```

A few things worth knowing about templates:

- They render **lazily**, at send time - compose now, render later.
- The engine is chosen by extension: `.twig` → Twig, `.latte` → Latte, anything else → your configured default (`renderer` option).
- An explicit `->html()` or `->text()` body always wins over a template, so you can set a default template and override it per message.

## Styling HTML and generating text parts

Two optional send-time enhancements, both off by default and both powered by libraries you only install if you want them:

| Feature             | Install                   | Config key       |
| ------------------- | ------------------------- | ---------------- |
| CSS inlining        | `pelago/emogrifier`       | `inline_css`     |
| Text part from HTML | `league/html-to-markdown` | `text_from_html` |

### Inline CSS into your HTML email

Gmail and most webmail clients strip `<style>` blocks - inline `style=""` attributes are the only styling they reliably honor. Writing those by hand is miserable; let [Emogrifier](https://github.com/MyIntervals/emogrifier) do it at send time:

```bash
composer require pelago/emogrifier
```

```php
MailPlugin::install([
    'dsns' => ['default' => 'smtp://user:pass@localhost:1025'],
    'inline_css' => true,
]);
```

With that on, every HTML body gets its CSS inlined just before sending - whether it came from a template or `->html()`. A message like `<style>p { color: red; }</style><p>Hi</p>` goes out as `<p style="color: red;">Hi</p>`.

To also inject shared styles into every email (brand colors, resets) without repeating them in each template, pass rules directly or point at a stylesheet file:

```php
'inline_css' => ['css_file' => __DIR__ . '/mail-styles/base.css'],
// or
'inline_css' => ['css' => '.button { background: #0a84ff; color: #fff; }'],
```

Full documents keep their structure; fragments stay fragments. Per-message control:

```php
$message->inlineCss();          // force inlining for this one message
$message->withoutInlineCss();   // skip it even when globally enabled
```

### Generate the text part from your HTML

Best practice is to send an HTML and a plain-text version together, but writing both is tedious. FlightMail can derive the text part from the final HTML automatically - no extra dependency required for basic conversion, since the converter ships with Symfony Mime:

```php
MailPlugin::install([
    'dsns' => ['default' => 'smtp://user:pass@localhost:1025'],
    'text_from_html' => true,       // Markdown when possible, plain otherwise
]);
```

Modes:

- `true` or `'auto'` - Markdown output if `league/html-to-markdown` is installed, otherwise simple tag-stripping.
- `'markdown'` - force Markdown (requires `composer require league/html-to-markdown`, headings become `==`, links `[text](url)`, bold `**bold**`).
- `'plain'` - always strip tags; works with zero extra packages.

Generation runs after rendering and CSS inlining, and only when the message has an HTML body but no text body - an explicit `->text()` or `->textTemplate()` always wins. Per-message control mirrors inlining:

```php
Flight::mail()->compose()
    ->to('someone@example.com')
    ->subject('Welcome!')
    ->template('welcome.html.twig', ['name' => 'Ryan'])
    ->send();                                   // text part generated for you

// ...and per-message overrides:
$message->textFromHtml('plain');                // force tag-stripping for this one
$message->withoutTextFromHtml();                // HTML-only email
```

If you enable a mode whose library isn't installed, you get a clear error telling you exactly which `composer require` to run - never silent degradation.

## Choosing a provider

Providers plug in through DSN strings. Install the bridge package, paste the DSN into `dsns`, done.

| Provider             | Install                                      | DSN example                                  |
| -------------------- | -------------------------------------------- | -------------------------------------------- |
| SMTP                 | built-in                                     | `smtp://user:pass@host:587`                  |
| Sendmail             | built-in                                     | `sendmail://default`                         |
| Dev/null (drop mail) | built-in                                     | `null://null`                                |
| Postmark             | `composer require symfony/postmark-mailer`   | `postmark+api://KEY@api.postmarkapp.com`     |
| Sendgrid             | `composer require symfony/sendgrid-mailer`   | `sendgrid+api://KEY@default`                 |
| Mailgun              | `composer require symfony/mailgun-mailer`    | `mailgun+https://KEY:DOMAIN@api.mailgun.net` |
| Amazon SES           | `composer require symfony/amazon-mailer`     | `ses+https://KEY:SECRET@default`             |
| Brevo                | `composer require symfony/brevo-mailer`      | `brevo+api://KEY@default`                    |
| MailerSend           | `composer require symfony/mailersend-mailer` | `mailersend+api://KEY@default`               |

The full list lives in the [Symfony Mailer docs](https://symfony.com/doc/current/mailer.html) - anything documented there works here unchanged.

### Multiple providers at once

Name each transport, then choose per message:

```php
MailPlugin::install([
    'dsns' => [
        'transactional' => 'postmark+api://KEY@api.postmarkapp.com',
        'bulk'          => 'smtp://user:pass@bulk.example.com:587',
    ],
    'from' => 'no-reply@example.com',
]);
```

```php
// No ->transport() call = first key in "dsns" ("transactional" here).
Flight::mail()->compose()->to('...')->text('receipt')->send();

// Opt into another route explicitly.
Flight::mail()->compose()->to('...')->text('newsletter')->transport('bulk')->send();
```

## Configuration reference

Everything is optional except `dsns`.

```php
MailPlugin::install([
    // REQUIRED - transport name => Symfony DSN.
    // The first entry is used when a message doesn't name one.
    'dsns' => [
        'default' => 'smtp://user:pass@localhost:1025',
    ],

    // Transport used when a message has no explicit ->transport() and
    // you don't want the first key. Must exist in "dsns".
    'default_transport' => 'default',

    // Global sender. String, Symfony Address, or ['email' => 'Name'].
    // Applied only when a message doesn't set its own ->from().
    'from' => ['no-reply@example.com' => 'My App'],

    // Default template engine: 'twig', 'latte', or a custom name.
    // Only consulted for templates whose extension isn't a registered renderer.
    'renderer' => 'twig',

    // Where templates live, searched in order; plus an optional cache dir.
    'templates' => [
        'paths' => [__DIR__ . '/mail-templates'],
        'cache' => __DIR__ . '/cache/mail',
    ],

    // Extra options passed straight to Twig\Environment.
    'twig' => ['options' => ['strict_variables' => true]],

    // Tweak the Latte engine at boot: fn(Latte\Engine $engine): void.
    'latte' => ['setup' => static fn (Latte\Engine $e) => $e->addExtension(new MyExtension())],

    // Inline CSS into HTML bodies at send time (needs pelago/emogrifier).
    // true processes each message's <style> blocks; an array can add shared CSS.
    'inline_css' => true,
    // 'inline_css' => ['css' => '.button { ... }', 'css_file' => __DIR__ . '/base.css'],

    // Generate the text part from the HTML body when none is set:
    // true / 'auto' = Markdown if league/html-to-markdown is installed, else plain;
    // 'markdown' or 'plain' forces a format; false (default) disables.
    'text_from_html' => true,

    // Custom DSN schemes, custom renderers, pre-send hooks (see below).
    'transport_factories' => [],
    'renderers' => [],
    'hooks' => [],

    // Optional plumbing handed to every transport.
    'event_dispatcher' => $dispatcher,  // Symfony MessageEvents
    'logger' => $psr3Logger,
]);
```

## Going further

Everything below is optional. The defaults cover most apps.

### Add a custom DSN scheme

Implement Symfony's `TransportFactoryInterface` and register it - then your own scheme works exactly like a built-in one:

```php
use ryanstubbs\FlightMail\MailPlugin;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MyCarrierFactory implements TransportFactoryInterface
{
    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'mycarrier';
    }

    public function create(Dsn $dsn): TransportInterface
    {
        // ... build a transport that talks to your carrier
    }
}

$plugin = MailPlugin::install(['dsns' => ['carrier' => 'mycarrier://key']]);
$plugin->addTransportFactory(new MyCarrierFactory());
```

### Add a custom template renderer

Anything that turns a template name plus params into a string qualifies:

```php
use ryanstubbs\FlightMail\MailPlugin;
use ryanstubbs\FlightMail\Render\RendererInterface;

$plugin = MailPlugin::install($config);

$plugin->addRenderer('markdown', fn (array $config): RendererInterface =>
    new MarkdownMailRenderer($config['templates']['paths'] ?? [])
);
```

```php
// Templates ending in .markdown now use it automatically:
Flight::mail()->compose()->to('...')->template('welcome.markdown', ['name' => 'Ryan'])->send();
```

### Run something right before sending

Hooks receive the finished message - after rendering, after defaults, just before the wire:

```php
$plugin->addHook(function (ryanstubbs\FlightMail\Message $message): void {
    $message->getHeaders()->addTextHeader('X-Mailer', 'MyApp/1.0');
});
```

### Events and logging

Hand over a Symfony event dispatcher and/or PSR-3 logger and every transport will use them:

```php
$plugin->eventDispatcher($dispatcher); // receives MessageEvent before each send
$plugin->logger($logger);              // transport-level logs
```

## API cheat sheet

```php
// Setup
MailPlugin::install($config)             // register on the global Flight app
MailPlugin::register($app, $config)      // register on a specific Engine
$mailer = Flight::mail();                // the shared Mailer instance

// Building messages
$mailer->compose(): Message
$message->to(...)->from(...)->subject(...)   // standard Symfony Mime methods
$message->text(string)                       // plain string body
$message->html(string)                       // HTML string body
$message->template($name, $params)           // HTML body from a template
$message->htmlTemplate($name, $params)       // alias of template()
$message->textTemplate($name, $params)       // text body from a template
$message->transport($name)                   // route via a named DSN
$message->inlineCss() / ->withoutInlineCss() // force on / off per message
$message->textFromHtml('plain')              // force a text format per message
$message->withoutTextFromHtml()              // HTML-only email
$message->send(): ?SentMessage               // render + enhance + send

// On the mailer itself
$mailer->send($message): ?SentMessage        // explicit alternative to $message->send()
$mailer->render($template, $params): string  // render without sending
$mailer->addHook(callable): static           // fn(Message $message): void
$mailer->transports(): TransportManager      // get() / has() / names()
$mailer->renderers(): RendererFactory        // create() / has() / add()
```

Since `Message` extends `Symfony\Component\Mime\Email`, every Symfony method you already know - `attach()`, `embed()`, `priority()`, `replyTo()` - works out of the box.

## Troubleshooting

**"No mail DSNs configured"**
You called `Flight::mail()` before registering the plugin, or the config array didn't include `dsns`. This error is deliberate - FlightMail refuses to guess where your mail should go rather than silently dropping it.

**"Unknown mail template renderer ..."**
You used a template whose engine isn't installed. Fix with `composer require twig/twig` or `composer require latte/latte`, or register a custom renderer named after the extension.

**"Unknown mail transport ..."**
A `->transport('name')` (or `default_transport`) doesn't match any key in `dsns`. Check spelling - the error lists the configured names.

**"CSS inlining requires pelago/emogrifier"**
You enabled `inline_css` (or called `->inlineCss()`) without installing the library. Fix with `composer require pelago/emogrifier`.

**"Markdown text parts require league/html-to-markdown"**
You forced `text_from_html` to `'markdown'` (or `->textFromHtml('markdown')`) without the library installed. Fix with `composer require league/html-to-markdown`, or use `true`/`'auto'`/`'plain'`, which degrade gracefully.

**Mail isn't arriving**
Point `dsns` at `null://null` to confirm the rest of your code works, then switch back to the real DSN. In DDEV, use `smtp://127.0.0.1:1025` and inspect messages in Mailpit at port 8025.

## Development

This repository is a DDEV project:

```bash
ddev start
ddev composer install
ddev composer test       # PHPUnit
ddev composer analyse    # PHPStan
```

## Contributing & license

Bug reports and pull requests welcome. MIT licensed - see [LICENSE](LICENSE).
