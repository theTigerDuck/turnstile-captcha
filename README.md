Turnstile Smart CAPTCHA
=================
(Please note this is a initial work, Have not done any Test yet not ideal for production environment)
Adds a "spam protection" field to SilverStripe userforms using Cloudflare's
[smart CAPTCHA](https://developers.cloudflare.com/turnstile) service.

## Requirements
* SilverStripe 5.x
* [SilverStripe Spam Protection
  4.x](https://github.com/silverstripe/silverstripe-spamprotection/)

## Installation
```
composer require silverstripe-terraformers/turnstile-captcha
```

After installing the module via composer or manual install you must set the spam
protector to TurnstileCaptchaProtector, this needs to be set in your site's config file
normally this is mysite/_config/config.yml.
```yml
SilverStripe\SpamProtection\Extension\FormSpamProtectionExtension:
    default_spam_protector: Terraformers\TurnstileCaptcha\Forms\TurnstileCaptchaProtector
```

Finally, add the "spam protection" field to your form by calling
``enableSpamProtection()`` on the form object.
```php
$form->enableSpamProtection();
```

## Configuration
Set the `site_key` and the `secret_key` via [environment variables](https://docs.silverstripe.org/en/5/getting_started/environment_management/).

```yml
SS_TURNSTILE_SITE_KEY=""
SS_TURNSTILE_SECRET_KEY=""
```

You can get these from your cloudflare account [refer to the turnstile documentation](https://developers.cloudflare.com/turnstile/). 

There are some optional configuration settings that can be
added to your site's yaml config (typically this is mysite/_config/config.yml).
```yml
Terraformers\TurnstileCaptcha\Forms\TurnstileCaptchaField:
    default_theme: "light" #Default theme color (optional, light or dark, defaults to auto)
    default_render_type: 'explicit' #Default setting for how to render the widget. See the "Render Type" section below.
```
TurnstileCaptchaField uses Guzzle to communicate with cloudflare. If you would like to change http connection settings (Eg proxy settings) you can configure your own HttpClient class via injector

```yml
SilverStripe\Core\Injector\Injector:
    Terraformers\TurnstileCaptcha\Http\HttpClient:
        class: App\HttpClient
```

## Adding field labels

If you want to add a field label or help text to the TurnstileCaptchaField field you can do so
like this:

```php
$form->enableSpamProtection()
    ->fields()->fieldByName('TurnstileCaptchaField')
    ->setTitle("Spam protection")
    ->setDescription("Please tick the box to prove you're a human and help us stop spam.");
```

### Commenting Module
When your using the
[silverstripe/comments](https://github.com/silverstripe/silverstripe-comments)
module you must add the following (per their documentation) to your \_config.php
in order to use Terraformers\TurnstileCaptcha on comment forms.

```php
CommentingController::add_extension('CommentSpamProtection');
```

## Render type
By default, the turnstyle widget will be rendered automatically. To change this you can set the render type.

This can be configured site-wide using the Config API
```yml
Terraformers\TurnstileCaptcha\Forms\TurnstileCaptchaField:
    default_render_type: 'explicit'
```

Or on a per form basis:
```php
$captchaField = $form->Fields()->fieldByName('TurnstileCaptchaField');
$captchaField->setRenderType('explicit');
```

With this configuration you will need to add your own javascript to render the widget. Refer to the [cloudflare documentation](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/#explicitly-render-the-turnstile-widget) for details.

## Reporting an issue

When you're reporting an issue please ensure you specify what version of
SilverStripe you are using i.e. 3.1.3, 3.2beta, master etc. Also be sure to
include any JavaScript or PHP errors you receive, for PHP errors please ensure
you include the full stack trace. Also please include how you produced the
issue. You may also be asked to provide some of the classes to aid in
re-producing the issue. Stick with the issue, remember that you seen the issue
not the maintainer of the module so it may take allot of questions to arrive at
a fix or answer.
