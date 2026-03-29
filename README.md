# Cloudflare Cirino

A lightweight WordPress plugin that automatically purges Cloudflare cache whenever content is created or updated.

## Overview

This plugin was built to simplify cache invalidation workflows for WordPress websites using Cloudflare edge caching.

Instead of manually clearing cache after content changes, the plugin listens for save events and sends a purge request to Cloudflare automatically.

## Features

- Automatic cache purge on content save or update
- Simple configuration from the WordPress admin
- Lightweight implementation
- Useful for websites that rely on Cloudflare full-page caching

## How it works

The plugin hooks into the WordPress save process and triggers a Cloudflare purge request whenever a post is updated.

This helps ensure that visitors receive the latest published content without requiring manual cache management.

## Setup

1. Install and activate the plugin.
2. Go to the plugin settings page in WordPress.
3. Add your Cloudflare Zone ID.
4. Save your settings.

## Use case

This plugin is helpful for websites that:

- use Cloudflare as a caching layer
- publish content frequently
- need a simple automated cache invalidation workflow

## Tech notes

This project is intentionally small and focused, aiming to solve a common operational problem in WordPress publishing environments.

## License

GPL v2 or later
