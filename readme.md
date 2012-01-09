# DisplayAnything #

A file upload and gallery tool for Silverstripe 2.4+. It's a simple replacement for the ImageGallery module and it's designed to get you up and running with minimum or no configuration and esoteric error messages.

DisplayAnything implements Ajax Upload (http://valums.com/ajax-upload/), a third party file upload handler.

The base level class is called UploadAnything which provides the upload functionality.

## Features ##
+ Handles file uploads via XHR or standard uploads.
+ Security: uses a configurable mimetype map, not file extensions, to determine an uploaded file type
+ Integration: uses PHP system settings for upload file size
+ Multiple file uploading in supported browsers (Note: not yet supported by Internet Explorer)
+ Drag and Drop in supported browsers (Chrome, Firefox, Safari and possibly Opera)
+ XHR file uploading
+ Has Zero Dependencies on the third-party Silverstripe modules ImageGallery, DataObjectManager and Uploadify
+ 100% Flash Free - no plugin crashes, HTTP errors, I/O errors or other futzing with incomprehensible Flash errors!
+ Import ImageGallery album items to a gallery
+ Uses jQuery bundled with Silverstripe
+ Well documented & clean code with extendable classes and overrideable methods
+ $_REQUEST not used
+ Uses Valum's Ajax Uploader (hat tip)
+ Drag and drop sorting of images & files in the gallery
+ File upload progress with cancel option

## State ##
+ Currently considered beta although we're eating our own dogfood and are happy with general stability - i.e test in a development setting, be aware it's in Beta and deploy if you are happy with the module.

## Bugs ##
Probably. Check the <a href="https://github.com/codem/DisplayAnything/issues">Issues list</a> and add feature requests and issues there.

## TODO ##
+ Client side reminder of file size (per Valums file uploader spec)
+ Testing of uploads in IE8+

## Why? ##
DisplayAnything was developed after implementing the ImageGallery module on a large, complex SilverStripe site which resulted in valuable time spent debugging DataObjectManager code and head-scratching Uploadify errors. Codem developed DisplayAnything to be a functional CMS replacement for the ImageGallery module.

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
		<dd><code>wget https://github.com/codem/DisplayAnything/zipball/master</code></dd>
	</dl>
</li>
<li>run /dev/build (admin privileges required) and possibly a ?flush=1</li>
<li>implement in the CMS (see 'CMS' below)</li>
<li>log into the CMS and start editing</li>
</ol>

## Migrating items from the ImageGallery gallery module ##
If DisplayAnything detects an  ImageGallery Album associated with the current page it will provide an Image Gallery Migration tab containing migration options. Migrated mages are copied rather than moved.
You can choose a albums from the list of album(s) provided and save the page, successfully imported items will appear in the file list. You can retry the migration at any time.

Once migration is complete you can remove the Image Gallery module as and when you wish.

## CMS ##
You can implement a DisplayAnything gallery using the normal getCmsFields() syntax on a Page type:

```php
class MyPage extends Page {
	
	public static $has_one = array(
		'SomeFile' => 'UploadAnythingFile',
		'SomeGallery' => 'DisplayAnythingGallery',
		'SomeVideoGallery' => 'DisplayAnythingYouTubeGallery',
	);
	
	public function getCmsFields() {
		$fields = parent::getCmsFields();
		
		//GALLERY per page
		$gallery = new DisplayAnythingGalleryField(
			$this,
			'SomeGallery',
			'DisplayAnythingGallery'
		);
		$gallery->SetTargetLocation('/some/path/to/a/gallery');//relative to ASSETS_PATH
		$fields->addFieldsToTab('Root.Content.Gallery', array($gallery));
		
		
		//SINGLE field - with a test to see if the page has been saved
		//NOTE: that single field uploads can be done with the core Silverstripe FileField (and is probably more stable at this point)
		if(!empty($this->ID)) {
			$uploader = new UploadAnythingField($this, 'SomeFile','Image');
			$uploader->SetMimeTypes(array('text/plain'));//this single file uploader only allows plain text uploads
		} else {
			$uploader = new LiteralField("PageNotSavedYet", "<p>The file may be uploaded after saving this page.</p>");
		}
		$fields->addFieldsToTab('Root.Content.Image', array($uploader));
		
		//YOUTUBE VIDEO gallery - a simple extension to the default gallery
		$gallery = new DisplayAnythingGalleryField(
			$this,
			'SomeVideoGallery',
			'DisplayAnythingYouTubeGallery'
		);
		$gallery->SetTargetLocation('videogallery');
		$fields->addFieldToTab('Root.Content.Videos', $gallery);
		
		return $fields;
	}
}
```
## Frontend Templates ##
+ Inumerable gallery plugins with varying licenses exist for image & file lists and viewing of images in a lightbox (Fancybox is good and open source). DisplayAnything stays light and does not bundle any of these galleries. It's up to you to implement the gallery the way you want it (this saves you having to undo & override any defaults DisplayAnything may set).
Here's an example Page control you may like to use as a starting point:

```php
<% if SomeImageGallery %>
	<% control SomeImageGallery %>
		<div id="ImageGallery">
			<h4>$Title</h4>
			<div class="inner">
				<% if GalleryItems %>
					<div id="GalleryItems">
							<ul id="GalleryList">
								<% control GalleryItems %>
									<li class="$EvenOdd $FirstLast"><a href="$URL" rel="page-gallery">$CroppedImage(90,90)</a></li>
								<% end_control %>
							</ul>
					</div>
				<% end_if %>
				<% include Clearer %>
			</div>
		</div>
	<% end_control %>
<% end_if  %>
```

## Support ##
+ Twitter : <a href="http://twitter.com/_codem">@_codem</a>
+ Github Issues list please for bug reports
+ Need extra help? <a href="http://codem.com.au">Codem can provide commercial support for this and other Silverstripe projects</a>

## Licenses ##
DisplayAnything is licensed under the Modified BSD License (refer license.txt)

This library links to Ajax Upload (http://valums.com/ajax-upload/) which is released under the GNU GPL and GNU LGPL 2 or later licenses

+ DisplayAnything is linking to Ajax Upload as described in Section 6 of the LGPL2.1 (http://www.gnu.org/licenses/lgpl-2.1.html)
+ You may modify DisplayAnything under the terms described in license.txt
+ The Copyright holder of DisplayAnything is Codem
+ The Copyright holder of Ajax Upload is Andrew Valums
+ Refer to javascript/file-uploader/license.txt for further information regarding Ajax Upload

