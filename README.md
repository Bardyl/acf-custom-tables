# ACF Custom Tables

## ⚠️ Caution

This plugin is a work in progress. Don't use it in a prod environment for now. No written tests (bouh!) and security is… basic.

## 📚 Definition

It provides another way to handle all [Advanced Custom Fields](http://advancedcustomfields.com) (basic and pro) in custom MySQL tables.

## ☕️ Context

Every people who worked with WordPress likes the simplicity of it, and ACF provides an easy way to add custom fields on posts, pages, and others things by doing a really good job. But while it take care of using the way WordPress works, it is not really adapted for hundreds of groups of dozens and dozens fields. It works, but front (not admin) performance is a mess when you need to request data. This is what we try to resolve with this plugin.

In fact, some plugins already exists to make what we want to achieve with this plugin, but they are not free, or they're too simple by handling only simple field, without pro fields, or fields working with sub-fields. This plugin is building all the required structure to handle all of them.

## 🚧 Progress

For now, it supports:
 - ✅ All basic fields (full support): text, textarea, number, range, email, url, password
 - ✅ All content fields (full support): image, file, wysiwyg, oembed, gallery
 - ✅ Group field (with recursive sub-fields)
 - Repeater field containing only basic fields (which store a single value), not recursively  

For fields marked as fully supported, the MySQL data type is optimized based on the field parameter.

## 💯 Next steps

The first goal is to manage all fields, recursively, no matter how they are built.

More to come, but feel free to add issues with examples of what you need.
