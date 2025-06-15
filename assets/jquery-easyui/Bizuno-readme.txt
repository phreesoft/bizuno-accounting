EasyUI integration notes:

Last revision: 2020-11-28


1. Function naming conflicts:

To prevent conflicts with jQueryUI and bootstrap and other frameworks, an aloais for jQuery has
been created. This means that all instances of (jQuery) at the end of each easuUI function needs to be changed

A global replace all within these files will work:

Search: (jQuery)
Replace (jqBiz)

jQuery runs in noConflict mode to prevent issues.

