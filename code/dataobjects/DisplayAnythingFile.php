<?php
/**
  * DisplayAnythingFile()
  * @note an UploadAnythingFile() that appears in a gallery
  * @note a DisplayAnythingFile does not have to be an image but as that's a common use it's the default. File type handling is determined by UploadAnythingField
 */
class DisplayAnythingFile extends UploadAnythingFile {
	
	static $has_one = array(
		'Gallery' => 'DisplayAnythingGallery',
	);
	
	static $defaults = array(
		'Visible' => 1,
	);
}

/**
 * DisplayAnythingYouTubeVideo()
 * @note a version of DisplayAnythingFile that represents a remote YouTube Video
 * @note this is an example of how you can extend DisplayAnything to add and modify functionality
 */
class DisplayAnythingYouTubeVideoFile extends DisplayAnythingFile {
	
	static $db = array (
		'VideoID' => 'Varchar(16)',
		'VideoWidth' => 'int',
		'VideoHeight' => 'int',
		'PrivacyEnhancedMode' => 'boolean',
		'ShowSuggestedVideos'=> 'boolean',
		'AutoPlay' => 'boolean',
		'UseImageInstead' => 'boolean',//if 1 the image will be shown in place of any video, SetWidth(width)
	);
	
	static $defaults = array (
		'UseImageInstead' => 0,
		'VideoWidth' => 420,
		'VideoHeight' => 315,
		'PrivacyEnhancedMode' => 0,
		'AutoPlay' => 0,
		'ShowSuggestedVideos' => 1,
	);
	
	
	public function ReplacementImage() {
		if($this->UseImageInstead == 1) {
			return $this->Thumnail('SetWidth', $this->Width);
		}
		return FALSE;
	}
	
	public function getCmsFields() {
		$fields = parent::getCmsFields();
		$fields->addFieldsToTab(
			'Root.VideoInformation',
			array(
				new TextField('VideoID', 'Video ID', $this->VideoID),
				new TextField('VideoWidth', 'Video Width', $this->VideoWidth),
				new TextField('VideoHeight', 'Video Height', $this->VideoHeight),
				new CheckboxField('UseImageInstead', 'Use the uploaded image in place of this video', $this->UseImageInstead == 1),
				
				new CheckboxField('PrivacyEnhancedMode', 'Show video in privacy enhanced mode', $this->PrivacyEnhancedMode == 1),
				new CheckboxField('AutoPlay', 'Autoplay video (not recommended)', $this->AutoPlay == 1),
				new CheckboxField('ShowSuggestedVideos', 'Show suggested videos at end of video', $this->ShowSuggestedVideos == 1),
				
			)
		);
		
		if(empty($this->VideoID)) {
			$preview = new LiteralField('YouTubePreviewField', '<p>To preview the video, please save this record with a VideoID (e.g http://youtube.com/<strong>VideoID</strong>) <a href="http://www.google.com/support/youtube/bin/answer.py?answer=171780">example</a></p>');
		} else {
		
			if($this->PrivacyEnhancedMode == 1) {
				$host = "www.youtube-nocookie.com";
			} else {
				$host = "www.youtube.com";
			}
			
			$preview = new LiteralField('YouTubePreviewField', "<div style=\"width:{$this->VideoWidth}px;margin:8px auto;\">" . $this->FlashCode() . "</div>");
		}
		
		$fields->addFieldToTab(
			'Root.VideoPreview',
			$preview
		);
		
		return $fields;
	}
	
	public function DimensionsFromWidth($requested_width, $requested_height = NULL) {
		$dimension = array(
			'width' => $this->VideoWidth,
			'height' => $this->VideoHeight,
		);
		
		if($requested_width > 0) {
			$aspect = ($requested_width / $this->VideoWidth);
			$dimension['width'] = $requested_width;
			if(is_null($requested_height)) {
				$dimension['height'] = round($this->VideoHeight * $aspect);
			} else {
				$dimension['height'] = $requested_height;
			}
		}
		
		return $dimension;
	}
	
	public function FlashCode($width = NULL, $height = NULL) {
		$host = $this->GetHost();
		$dimensions = $this->DimensionsFromWidth($width, $height);
		return
<<<HTML
	<object width="{$dimensions['width']}" height="{$dimensions['height']}">
		<param name="movie" value="//{$host}/v/{$this->VideoID}?theme=light&version=3&autohide=1&showinfo=0&autoplay={$this->AutoPlay}&modestbranding=1"></param>
		<param name="allowScriptAccess" value="always"></param>
			<embed src="//{$host}/v/{$this->VideoID}?theme=light&version=3&autohide=1&showinfo=0&autoplay={$this->AutoPlay}&modestbranding=1"
				type="application/x-shockwave-flash"
				allowscriptaccess="always"
				width="{$dimensions['width']}" height="{$dimensions['height']}"></embed>
	</object>
HTML
;
	}
	
	protected function GetHost() {
		if($this->PrivacyEnhancedMode == 1) {
			$host = "www.youtube-nocookie.com";
		} else {
			$host = "www.youtube.com";
		}
		
		return $host;
	}
	
	public function CanShowVideo() {
		if($this->UseImageInstead == 1) {
			return FALSE;
		} else if(!$this->VideoID) {
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	
	/**
	 * @note Chrome show hidden iframes in top left corner
	 */
	public function EmbedCode($width =  NULL, $height = NULL) {
		if($this->VideoID) {
			$dimensions = $this->DimensionsFromWidth($width, $height);
			$host = $this->GetHost();
			return "<iframe class=\"video\"
				type=\"text/html\"
				width=\"{$dimensions['width']}\"
				height=\"{$dimensions['height']}\"
				src=\"//{$host}/embed/{$this->VideoID}\"
				frameborder=\"0\">
				</iframe>";
		} else {
			return "";
		}
	}
	
}
?>