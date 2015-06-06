Description
===========

The Text or Entity module provides a hybrid field type that accepts either text
or entity reference as its value, allowing fields to optionally reference
arbitrary entities via their label.

Features
========

- Allowed entity type / bundles and maximum length of text value are configured
  in field settings.
- Autocomplete widget combines results based on previously entered text values
  and labels of allowed entity references. Limit on the number of suggestions
  and match operator (starts with / contains) are configured in widget settings.
- Field formatter optionally displays label as a link to the referenced entity
  if one exists, or as text otherwise.
- Inserting, updating or deleting referenced entities also updates field values
  where they are referenced, so changing the node title also changes the text
  value on a field referencing that node as would be expected.
- Support for the Real Name module is transparent to the user.
- Views integration exposes direct and reverse relationships just like regular
  entity references.
- Feeds integration allows importing either text value(s) or entity ID.

Requirements
============

- Entity API 1.x
  https://www.drupal.org/project/entity

Recommended modules
===================

- Feeds 2.x
  https://www.drupal.org/project/feeds
- Realname 1.x
  https://www.drupal.org/project/realname
- Views 3.x
  https://www.drupal.org/project/views

Similar modules
===============

- This module was first inspired by the Text or Nodereference module, but
  overcomes its limitation by integrating with Entity API, Views and Real Name.
  See https://www.drupal.org/project/text_noderef
- Entity reference offers more flexibility when it comes to entity selection
  (i.e. filter by an entityreference view), but doesn't allow text values with
  optional referencing. Also, with Text or Entity, importers can handle entity
  labels directly without the need for feeds tampering.
