# Grav Syndication Feed Plugin

`feed` is a [Grav](http://github.com/getgrav/grav) plugin and allows Grav to generate feeds of your pages.

This plugin supports both __Atom 1.0__ and __RSS__ feed types. Enabling is very simple. just install this plugin in the `/user/plugins/`` folder in your Grav install. By default, the plugin is enabled and provides some default values.

# Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `feed`.

You should now have all the plugin files under

	/your/site/grav/user/plugins/feed

>> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav), the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) plugins, and a theme to be installed in order to operate.

# Usage

The feeds work for pages that have sub-pages, for example a blog list view. If your page has a `sub_pages` header variable that defines a taxonomy or `true` to display sub-pages, then the RSS plugin will automatically be enabled. Simply append either `feed:atom` or `feed:rss` to the url.

eg:

```
http://www.mysite.com/blog/feed:atom
```

# Config Defaults

```
limit: 10
description: My Feed Description
lang: en-us
length: 500
```

You can override any of the default values by setting one or more of these in your blog list page where `sub_pages` is defined. For example:

```
title: Sample Blog
content:
    items: @self.children
    limit: 5
    pagination: true
feed:
    limit: 15
    description: Sample Blog Description
```
