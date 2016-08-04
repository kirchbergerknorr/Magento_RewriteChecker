# Kirchbergerknorr RewriteChecker

This module checks if all urls for categories and products are rewritten and if not, trigger the url rewrite generation.

## Installation

Add `require` and `repositories` sections to your composer.json as shown in example below and run `composer update`.

*composer.json example*

```
{
    ...
    
    "repositories": [
        {"type": "git", "url": "https://github.com/kirchbergerknorr/Magento_RewriteChecker"},
    ],
    
    "require": {
        "kirchbergerknorr/Magento_RewriteChecker": "*"
    },
    
    ...
}
```


## Support

Please [report new bugs](https://github.com/kirchbergerknorr/kirchbergerknorr/Kirchbergerknorr_RewriteChecker/issues/new).

## How to use?

Go to System->Configuration->Kirchbergerknorr->RewriteChecker and configure the module that it fits your needs.