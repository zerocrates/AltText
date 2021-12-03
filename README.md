# Alt Text

An Omeka S module to allow users to specify custom alt text for media.

## This module is no longer required with Omeka S 3.1.0 and up

Omeka S 3.1.0 integrates the features of this module directly in the core,
accessible from the [Media edit form's Advanced tab](https://omeka.org/s/docs/user-manual/content/media/#advanced).

The latest version of this module (1.3.0) provides a feature to copy all
alt texts set using the module to the core. Once the texts are copied over,
this module can be uninstalled. (Note: if using the "Alt text property"
setting of this module, the core's equivalent "Media alt text property"
global setting should be set to the same property for the same effect.)

Users of Omeka S 3.1.0+ who upgraded from prior versions should only upgrade
this module and then migrate to the core's alt text feature. For fresh
installations of Omeka S 3.1.0+ or ones which never previously used this
module, there is no benefit to installing this module.

Users of older versions of Omeka S can use the prior versions of this module
listed on the Omeka S Modules directory appropriate to their Omeka S version.

## Usage

The Alt Text module offers two ways to specify the alt text for a media:
manually writing out the desired text and automatically pulling from a
piece of metadata.

To manually specify alt text, go to the edit form for a media. The module adds
a tab named "Alt Text" with a single input for adding the alt text.

To set up automatic alt text pulling from metadata, go to the module's Configure
screen. The input "Alt text property" lets an administrator select a property to
pull from for alt text. This "automatic" alt text will only be used if no manual
text has been specified for a media. Note: the automatic alt text feature only
pulls from media metadata, not that of the parent item.
