# DisplayAnything #

DisplayAnything is both a file and image gallery module for the SilverStripe CMS. By default, it's shipped as an image gallery module.
It can replace the standard ImageGallery module, migrate images from it and it's designed to get you up and running with minimum or no configuration and esoteric error messages.

DisplayAnything implements the client-side features provided by File Uploader (https://github.com/valums/file-uploader), a third party Javascript library.

The base level class is called UploadAnything which provides the upload functionality.

## New Features ##
+ Allows for watermarking of images (see Watermarking below)
+ Better file name handling
+ Various performance enhancements and UI updates
+ Some IE9 improvements to the UI (IE9 handling not feature complete - fork me to help out!)

## Features ##
+ Handles file uploads via XHR or standard uploads.
+ Import ImageGallery album items to a DisplayAnything gallery!
+ Security: uses a configurable mimetype map, not file extensions, to determine an uploaded file type
+ Integration: uses PHP system settings for upload file size
+ Multiple file uploading in supported browsers (Note: Internet Explorer < 9 does not support multiple file uploads)
+ Drag and Drop in supported browsers (Chrome, Firefox, Safari and possibly Opera)
+ XHR file uploading
+ Has Zero Dependencies on the third-party Silverstripe modules ImageGallery, DataObjectManager and Uploadify
+ 100% Flash Free - no plugin crashes, HTTP errors, I/O errors or other futzing with incomprehensible Flash errors!
+ Uses jQuery bundled with SilverStripe
+ Well documented & clean code with extendable classes and overrideable methods
+ $_REQUEST not used
+ Uses Valum's File Uploader (hat tip)
+ Drag and drop sorting of images & files in the gallery
+ File upload progress with cancel option

## State ##
+ Currently considered beta although we're eating our own dogfood and are happy with general stability - i.e test in a development setting, be aware it's in Beta and deploy if you are happy with the module.

## TODO ##
+ Client side reminder of file size (per Valums file uploader spec)
+ Testing of uploads in IE8+

## Why? ##
DisplayAnything was developed after implementing the ImageGallery module on a large, complex SilverStripe site which resulted in valuable time spent debugging DataObjectManager code and head-scratching Uploadify errors.

Codem developed DisplayAnything to be a functional CMS replacement for the SilverStripe ImageGallery module.

## MimeTypes ##
DisplayAnything comes preconfigured to accept image uploads (GIF, PNG, JPG). When used as a gallery, a usage tab is made available where you can add and edit the current gallery usage.

## Installing ##
<ol>
<li>cd /path/to/your/silverstripe/site</li>
<li>Grab the source:
	<dl>
		<dt>Git</dt>
		<dd><code>git clone git@github.com:codem/DisplayAnything.git display_anything</code></dd>
		<dt>Bzr (requires bzr-git) - note the / in the path</dt>
		<dd><code>bzr branch git://git@github.com/codem/DisplayAnything.git display_anything</code></dd>
		<dt>Download</dt>
		<dd><code>wget --output-document=display_anything.zip https://github.com/codem/DisplayAnything/zipball/master</code></dd>
	</dl>
	<br />In all cases the module source code should be located in a directory called 'display_anything'
</li>
<li>run /dev/build (admin privileges required) and possibly a ?flush=1</li>
<li>implement in the CMS (see 'CMS' below)</li>
<li>log into the CMS and start editing</li>
</ol>

## Migrating items from the ImageGallery gallery module ##
If DisplayAnything detects an  ImageGallery Album associated with the current page it will provide an Image Gallery Migration tab containing migration options. Migrated images are copied rather than moved.
You can choose a albums from the list of album(s) provided and save the page, successfully imported items will appear in the file list. You can retry the migration at any time.

Once migration is complete you can remove the Image Gallery module as and when you wish.

## CMS implementation ##
View the <a href="./examples">example directory</a> for some sample page, dataobject and template implementations.

## Templates ##
Innumerable gallery plugins with varying licenses exist for image & file lists and viewing of images in a lightbox (Fancybox is good and open source).

By design, DisplayAnything avoids being a kitchen sink, stays light and does not bundle any of these plugins. It's up to you to implement the gallery the way you want it (this saves you having to undo & override any defaults DisplayAnything may set).

View the <a href="./examples/templates">example directory</a> for some sample layouts related to the pages in the examples section.

### Ordered Gallery Items ###
You can implement ordered galleries in your frontend template to match yours or someone else's drag and drop admin work on the Gallery. Simply change "GalleryItems" to "OrderedGalleryItems" in the template example above.

## Watermarking ##
To implement watermarking, use the following image template/html:
```php
<li class="$EvenOdd $FirstLast"><a href="$URL" rel="page-gallery">$WatermarkCroppedImage(90,90)</a></li>
```
You can use any Silverstripe image resizing method supported (SetHeight, SetWidth, CroppedImage, PaddedImage, SetSize) but prefixed with "Watermark".

The module ships with a watermark image called "_wm.png". To implement your own, add an image called "_wm.png" to a directory named the same as your theme. For example, if your theme is "green", add a file of that name to document_root/green/images/.

Watermarking is only enabled if you use the Watermark prefixed template controls.

## Watermark configuration options ###
Use the following in your site config:
+ WatermarkedImage::$opacity (0-100)
+ WatermarkedImage::$position (tr, tl, br, bl). Example br anchors the watermark image to the bottom right of the source image.
+ WatermarkedImage::$padding_x (pixel padding from image edge in the x-axis)
+ WatermarkedImage::$padding_y (pixel padding from image edge in the y-axis)

## Watermark notes ###
+ Uses GD
+ 8 bit PNGs only
+ The watermark source image is not resized
+ The original image is not watermarked
+ WatermarkedImageDecorator decorates Image

## Support ##
+ Twitter : <a href="http://twitter.com/_codem">@_codem</a>
+ Github <a href="https://github.com/codem/DisplayAnything/issues">Issues list please for bug reports & feature requests</a>
+ Need extra help? <a href="http://codem.com.au">Codem can provide commercial support for this and other Silverstripe projects</a>

## Common Problems ##
+ Javascript not running - ensure the source code is located in a directory called 'display_anything'
+ Internet Explorer bugs - probably, although it should work in IE9. We don't support 7 or lower and intensive testing in 8+ has not been completed. You are welcome to try it out in IE8+ and report issues.

## Licenses ##
DisplayAnything is licensed under the Modified BSD License (refer license.txt)

This library links to Ajax Upload (http://valums.com/ajax-upload/) which is released under the GNU GPL and GNU LGPL 2 or later licenses

+ DisplayAnything is linking to Ajax Upload as described in Section 6 of the LGPL2.1 (http://www.gnu.org/licenses/lgpl-2.1.html)
+ You may modify DisplayAnything under the terms described in license.txt
+ The Copyright holder of DisplayAnything is Codem
+ The Copyright holder of Ajax Upload is Andrew Valums
+ Refer to javascript/file-uploader/license.txt for further information regarding Ajax Upload

