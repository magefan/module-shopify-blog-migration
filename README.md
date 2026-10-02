# Magento 2 Blog Export to Shopify by Magefan

[![Total Downloads](https://poser.pugx.org/magefan/module-shopify-blog-migration/downloads)](https://packagist.org/packages/magefan/module-shopify-blog-migration)
[![Latest Stable Version](https://poser.pugx.org/magefan/module-shopify-blog-migration/v/stable)](https://packagist.org/packages/magefan/module-shopify-blog-migration)

Magento 2 Blog Export to Shopify by Magefan is an extension that helps you migrate Magento blog to Shopify blog without manually recreating your blog posts there. The extension exports blog content from Magefan [Magento Blog](https://magefan.com/magento2-blog-extension), Mageplaza Blog, or Mirasvit Blog and sends it to the Magefan [Blog on Shopify](https://apps.shopify.com/magefan-blog) or to native Shopify blog through the Magefan [Shopify Blog Import](https://apps.shopify.com/blog-import) app.

It is designed for Magento store owners, developers, and agencies migrating an ecommerce store from Magento 2 to Shopify while keeping their existing blog content.

<img src="https://cm.magefan.com/mf_webp/png/media/blog/magefan-blog-export-to-shopify-banner.webp">

## Supported Magento Blog Extensions
The extension supports three blog extensions for Magento for blog migration:

* Magefan Blog
* Mageplaza Blog
* Mirasvit Blog

You can select the blog extension you are using when starting the export. On multi-store Magento installations, you can also select the relevant store view.

## What Gets Exported from Magento Blog to Shopify?
Magento 2 Blog Export to Shopify transfers the main elements of your existing Magento blog to Shopify, including:

* Blog posts
* Blog categories
* Post tags
* Featured images
* Post images
* SEO data
* Post content
* Post status

Categories are converted into separate blogs in Shopify (if you migrate to default Shopify Blog or regular categories if you migrate to Magefan Blog on Shopify, allowing the exported content to be organized according to the original Magento blog structure. 

> **Note:** Disabled posts are imported as hidden content in default Shopify blog.

## Move Your Magento Blog to Shopify
Migrating a Magento store to Shopify often means moving more than products, customers, and orders. Your blog can contain years of content, images, and SEO information that you may want to keep after changing platforms.

Magento 2 Blog Export to Shopify by Magefan handles the blog portion of the migration by exporting your existing Magento blog and transferring it to Shopify.

Instead of manually recreating every article in the Shopify admin, you can export the content from your existing Magento blog and import it into Shopify through Magefan's [Shopify Blog Import](https://apps.shopify.com/blog-import) app or the Magefan [Blog app for Shopify](https://apps.shopify.com/magefan-blog) directly.


## How Does the Blog Export to Shopify Work?
The extension connects your Magento 2 store with the Magefan Blog Import app for Shopify and Magefan's Shopify Blog.

The process is:
1. Install Magefan Blog Export for Shopify (this plugin).
2. Install the Magefan Blog Import app or Magefan's Shopify Blog in your Shopify admin.
3. Copy the connection key from the Shopify app.
4. Enter the key in your Magento admin.
5. Select the Magento blog extension you want to export.
6. Select the store view if your Magento installation has multiple store views.
7. Start the export.

The Magento extension sends the blog data to the Shopify app, which creates the corresponding content in the default Shopify blog.

## Images Are Uploaded Directly to Shopify
Blog migration should not require your Magento website to remain publicly accessible just to retrieve its images.

Magento 2 Blog Export to Shopify uploads images stored in pub/media directly to Shopify during the export. This allows the migration to work even when the Magento store is not publicly reachable.


## Installation of the Magento Blog Export to Shopify plugin
Install the Magento 2 Blog Export for Shopify with Composer from the Magento 2 root directory:
```
composer require magefan/shopify-blog-migration
```
Then run the Magento setup commands:
```
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

To install thie plugin via FTP using archive, first install the [Magefan Community Extension](https://github.com/magefan/module-community/) module. 

Then download [Export plugin ZIP archive](https://github.com/magefan/module-shopify-blog-migration/archive/main.zip) and extract the files.

Finally, inn your Magento 2 root directory, create folder app/code/Magefan/ShopifyBlogExport and copy files and folders from archive to that folder.

The run the commands:
```
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

After installation, log in to Magento admin and complete the Shopify connection before starting the export.

## Configuration and Blog Export
### Step 1: Install the Shopify Blog Import app
Install [Magefan Blog Import](https://apps.shopify.com/blog-import) app or Magefan's [Blog App for Shopify](https://apps.shopify.com/magefan-blog). The copy the connection key provided by the app.

### 2. Open the Magento export tool
In Magento Admin, go to Content > Shopify Blog Import > Export Blog to Shopify and paste the connection key into the corresponding field.

### 3. Select your Magento blog
Choose the Magento blog extension you want to migrate:
* Magefan Blog
* Mageplaza Blog
* Mirasvit Blog

If your Magento installation contains multiple store views, select the store view from which you want to export the blog.

### 4. Start the migration
Click Start Export to begin transferring your Magento blog to Shopify.

The exported content is processed by the Magefan Blog Import app for Shopify and added to the native Shopify blog or by Magefan Blog app and added to Magefan's Blog app.


## Open Source
Magefan Blog Import for Shopify is open-source software. Contributions, bug reports, and improvements are welcome.

## Support
If you have any issues, please [contact us](mailto:support@magefan.com)
then if you still need help, open a bug report in GitHub's
[issue tracker](https://github.com/magefan/magefan-blog-export/issues).

## License
The code is licensed under [EULA](https://magefan.com/end-user-license-agreement).
    
## [Magento Extensions](https://magefan.com/magento-2-extensions) by Magefan

### Magento 2 SEO Extensions

* [Magento SEO](https://magefan.com/magento-2-seo-extension)
* [Magento 2 Rich Snippets](https://magefan.com/magento-2-rich-snippets)
* [Magento 2 HTML Sitemap](https://magefan.com/magento-2-html-sitemap-extension)
* [Magento 2 XML Sitemap](https://magefan.com/magento-2-xml-sitemap-extension)
* [Magento 2 Twitter Cards](https://magefan.com/magento-2-twitter-cards-extension)
* [Magento Open Graph Tags](https://magefan.com/magento-2-open-graph-extension-og-tags)

### [Magento 2 Google Extensions](https://magefan.com/magento-2-extensions/google-extensions)

* [Magento Google Tag Manager](https://magefan.com/magento-2-google-tag-manager)
* [Magento 2 Google Analytics 4](https://magefan.com/magento-2-google-analytics-4)
* [Magento Google Shopping Feed](https://magefan.com/magento-2-google-shopping-feed-extension)
* [Magento Google Customer Reviews](https://magefan.com/magento-2-google-customer-reviews)
* [Magento 2 Google Indexing](https://magefan.com/magento-2-google-indexing-api)

### [Magento Speed Optimisation Extensions](https://magefan.com/magento-2-extensions/speed-optimization)

* [Magento 2 Google Page Speed Optimizer](https://magefan.com/magento-2-google-page-speed-optimizer)
* [Magento 2 WebP Images](https://magefan.com/magento-2-webp-optimized-images)
* [Magento Full Page Cache Extension](https://magefan.com/magento-2-full-page-cache-warmer)
* [Magento 2 Lazy Load Images](https://magefan.com/magento-2-image-lazy-load-extension)
* [Magento 2 Defer JavaScript](https://magefan.com/rocket-javascript-deferred-javascript)

### [Magento Admin Extensions](https://magefan.com/magento-2-extensions/admin-extensions)

* [Magento 2 Dynamic Category](https://magefan.com/magento-2-dynamic-categories)
* [Magento 2 Size Chart](https://magefan.com/magento-2-size-chart)
* [Magento 2 Security Extension](https://magefan.com/magento-2-security-extension)
* [Magento 2 Admin Action Log](https://magefan.com/magento-2-admin-action-log)
* [Magento Extended Product Grid](https://magefan.com/magento-2-product-grid-inline-editor)
* [Magento 2 Product Tabs](https://magefan.com/magento-2/extensions/product-tabs)
* [Magento 2 Product Widget](https://magefan.com/magento-2-product-widget)
* [Magento 2 Email Attachments](https://magefan.com/magento-2-email-attachments)
* [Magento 2 Admin View](https://magefan.com/magento-2-admin-view-extension)
* [Magento 2 Email Notifications](https://magefan.com/magento-2-admin-email-notifications)
* [Magento 2 Login As Customer](https://magefan.com/login-as-customer-magento-2-extension)

### [Magento Order Management Extensions](https://magefan.com/magento-2-extensions/order-management)

* [Magento Order Editor](https://magefan.com/magento-2-edit-order-extension)
* Better [Magento 2 Order Grid](https://magefan.com/magento-2-better-order-grid-extension)
* [Magento 2 Guest to Customer](https://magefan.com/magento2-convert-guest-to-customer)
* [Magento POS System](https://magefan.com/magento-pos-system)

### Magento 2 Blog Extensions

* [Magento 2 Blog Extension](https://magefan.com/magento2-blog-extension)
* [Magento 2 Multi Blog](https://magefan.com/magento-2-multi-blog-extension)

### [Magento Marketing Extensions](https://magefan.com/magento-2-extensions/marketing-automation)

* [Magento 2 Facebook Pixel](https://magefan.com/magento-2-facebook-pixel-extension)
* [Magento TikTok Pixel](https://magefan.com/magento-2-tiktok-pixel)
* [Magento 2 Dynamic Blocks](https://magefan.com/magento-2-cms-display-rules-extension) and Pages
* [Magento 2 Cookie Consent](https://magefan.com/magento-2-cookie-consent)
* [Magento 2 Base Price](https://magefan.com/magento-2-base-price)
* [Magento 2 Price History](https://magefan.com/magento-2-price-history)
* [Magento 2 Mautic Extension](https://magefan.com/magento-2-mautic-extension)
* [Magento 2 YouTube Video](https://magefan.com/magento2-youtube-extension)

### [Magento Promotions Extensions](https://magefan.com/magento-2-extensions/promotions-extensions)

* [Magento 2 Automatic Related Products](https://magefan.com/magento-2-automatic-related-products)
* [Magento 2 Product Labels](https://magefan.com/magento-2-product-labels)
* [Magento 2 Coupon Code Extension](https://magefan.com/magento-2-coupon-code-link)

### [Magento 2 Multi-Language Extensions](https://magefan.com/magento-2-extensions/multi-language-extensions)

* [Magento 2 Hreflang Tags](https://magefan.com/magento2-alternate-hreflang-extension)
* [Magento 2 Currency Switcher](https://magefan.com/magento-2-currency-switcher-auto-currency-by-country)
* [Magento 2 Language Switcher](https://magefan.com/magento-2-auto-language-switcher)
* [Magento 2  Store Switcher](https://magefan.com/magento-2-geoip-switcher-extension)
* [Magento 2 Translation Extension](https://magefan.com/magento-2-translation-extension)

### [Developers Tools](https://magefan.com/magento-2-extensions/developer-tools)

* [Magento Zero Downtime Deployment](https://magefan.com/blog/magento-2-zero-downtime-deployment)
* [Magento 2 Cron Schedule](https://magefan.com/magento-2-cron-schedule)
* [Magento 2 CLI Extension](https://magefan.com/magento2-cli-extension)
* [Magento 2 Conflict Detector](https://magefan.com/magento2-conflict-detector)

### [Shopify Apps](https://magefan.com/shopify/apps) by Magefan

* [Shopify Login As Customer](https://apps.shopify.com/login-as-customer)
* [Shopify Blog](https://apps.shopify.com/magefan-blog)
* [Shopify Size Chart](https://magefan.com/shopify/apps/size-chart)
* [Shopify Google Indexer](https://magefan.com/shopify/apps/google-indexing)
* [Shopify Product Feeds](https://magefan.com/shopify/apps/product-feed)
* [Shopify Server GTM & GA4](https://magefan.com/shopify/apps/gtm-and-ga4)

### [Magento 2 Services](https://magefan.com/services) by Magefan

* [Magento Speed Optimization Service](https://magefan.com/magento-speed-optimization-service)
* [Magent SEO Service](https://magefan.com/magento-2-seo-service)
* [Custom Magento Development](https://magefan.com/custom-development)
* [Magento Installation Service](https://magefan.com/installation-service)
