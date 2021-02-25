# Alt Text

An Omeka S module to allow users to specify custom alt text for media.

The current version of the Alt Text module requires at least Omeka S 3.0.0.

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
