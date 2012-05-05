<?php
class WatermarkedImage extends Image {

	public static $opacity = 50;//default opacity
	public static $position = "tr";
	public static $padding_x = 5;
	public static $padding_y = 5;
	
	private $watermark_image_path;
	private $can_watermark = TRUE;//on by default. SiteLocale can turn this off
	
	private function watermark_config() {
		$this->get_watermark_image();
	}
	
	/**
	 * Generate an image on the specified format. It will save the image
	 * at the location specified by cacheFilename(). The image will be generated
	 * using the specific 'generate' method for the specified format.
	 * @param string $format Name of the format to generate.
	 * @param string $arg1 Argument to pass to the generate method.
	 * @param string $arg2 A second argument to pass to the generate method.
	 *
	 * @note this decoration of the original function watermarks the image, if required, but does not alter the original
	 */
	function generateFormattedImage($format, $arg1 = null, $arg2 = null) {
	
		$cacheFile = $this->cacheFilename($format, $arg1, $arg2);
		
		//the source file (not a previously watermarked thumb)
		$path  = Director::baseFolder()."/" . $this->Filename;
		if(!is_file($path)) {
			return FALSE;
		}
		
		$gd = new GD($path);
		
		if($gd->hasGD()) {
			$generateFunc = "generate$format";
			if($this->hasMethod($generateFunc)) {
				if($gd = $this->$generateFunc($gd, $arg1, $arg2)) {
					try {
						$this->watermark_config();
						if($this->can_watermark) {
							$this->watermark(&$gd);
						}
						$gd->writeTo(Director::baseFolder()."/" . $cacheFile);
					} catch (Exception $e) {
						USER_ERROR("WatermarkedImage::generateFormattedImage - watermarking failed: {$e->getMessage()}.",E_USER_WARNING);
					}
				}
			} else {
				USER_ERROR("WatermarkedImage::generateFormattedImage - Image $format function not found.",E_USER_WARNING);
			}
		}
	}
	
	public function get_watermark_image() {
		$this->watermark_image_path = FALSE;
		//try to get it from sitelocale
		if(class_exists('SiteLocale')) {
			$settings = SiteLocale::GetCurrentDomainRecord();
			$image = $settings->WatermarkImage();
			$this->can_watermark = ($settings->WatermarkEnabled == 1);//has site locale turned it off ?
			self::$opacity = $settings->WatermarkTransparency;
			if(!empty($image->ID)) {
				$this->watermark_image_path = $image->getFullPath();
			}
		}
		
		if(!$this->watermark_image_path || !is_readable($this->watermark_image_path)) {
			//try to get it form the theme
			$theme = SSViewer::current_theme();
			if($theme) {
				$this->watermark_image_path = Director::baseFolder() . '/' . $theme . '/images/_wm.png';
			}
		}
		
		if(!$this->watermark_image_path || !is_readable($this->watermark_image_path)) {
			//get it from this directory
			$this->watermark_image_path = Director::baseFolder() . '/display_anything/images/_wm.png';
		}
		
		//test opacity is sane
		if(self::$opacity < 0 || self::$opacity > 100) {
			self::$opacity = 50;//fallback if nothing sensible is set
		}
		
		return $this->watermark_image_path;
		
	}
	
	public function watermark($gd) {
		
		if(is_readable($this->watermark_image_path)) {
			$meta = getimagesize($this->watermark_image_path);
		} else {
			throw new Exception("Watermark file {$this->watermark_image_path} is not readable");
		}
		
		if(empty($meta[2]) || $meta[2] != IMAGETYPE_PNG) {
			throw new Exception("Watermark file {$this->watermark_image_path} is not a valid PNG file");
		}
		
		//get watermark image source
		$watermark = imagecreatefrompng($this->watermark_image_path);
		$watermark_width = $meta[0];
		$watermark_height = $meta[1];
		
		$base = imagecreatetruecolor($watermark_width, $watermark_height);
		if(!$base) {
			throw new Exception("Could not create watermark image");
		}
		
		//maintain alpha blending
		imagealphablending($base, FALSE);
		imagesavealpha($base, TRUE);
		
		//centre the image
		switch(self::$position) {
			case 'tl':
				$dest_x = self::$padding_x;
				$dest_y = self::$padding_y;
				break;
			case 'tr':
				$dest_x = (($gd->getWidth() - $watermark_width) - self::$padding_x);
				$dest_y = self::$padding_y;
				break;
			case 'bl';
				$dest_x = self::$padding_x;
				$dest_y = (($gd->getHeight() - $watermark_height) - self::$padding_y);
				break;
			case 'br':
				$dest_x = (($gd->getWidth() - $watermark_width) - self::$padding_x);
				$dest_y = (($gd->getHeight() - $watermark_height) - self::$padding_y);
				break;
			case 'centre':
			case 'center':
			default:
				$dest_x = (($gd->getWidth() - $watermark_width)/2);
				$dest_y = (($gd->getHeight() - $watermark_height)/2);
				break;
		}
		
		//merge the watermark into the current source GD dile
		$result = imagecopymerge($gd->getGD(), $watermark, $dest_x, $dest_y, 0, 0, $watermark_width, $watermark_height, self::$opacity);
		
		if(!$result) {
			throw new Exception("Could not merge watermark into source image");
		}
		
		return TRUE;
		
	}
	
}


class WatermarkedImageDecorator extends DataObjectDecorator {

	private function getWatermarkedImage() {
		if($this->owner instanceof Image) {
			//return a watermarkable Image object, extending Image
			return new WatermarkedImage($this->owner->getAllFields());
		}
		return FALSE;
	}
	
	private function applyResize($method, $width = 0, $height = 0) {
		if($image = $this->getWatermarkedImage()) {
			switch($method) {
				case 'SetWidth':
				case 'SetHeight':
				case 'PaddedImage':
				case 'SetSize':
				case 'CroppedImage':
					return $image->getFormattedImage($method, $width, $height);
					break;
				default:
					return FALSE;
					break;
			}
		}
		return FALSE;
	}

	public function WatermarkSetWidth($width) {
		return $this->applyResize('SetWidth',$width);
	}

	public function WatermarkSetHeight($height) {
		return $this->applyResize('SetHeight',$height);
	}

	public function WatermarkPaddedImage($width, $height) {
		return $this->applyResize('PaddedImage',$width, $height);
	}

	public function WatermarkCroppedImage($width, $height) {
		return $this->applyResize('CroppedImage',$width, $height);
	}

	public function WatermarkSetSize($width, $height) {
		return $this->applyResize('SetSize',$width, $height);
	}
	
	public function WatermarkLink() {
		$wm = $this->getWatermarkedImage();
		$copy = $wm->SetSize($wm->getWidth(), $wm->getHeight());
		if($copy) {
			unset($wm);
			return $copy->Link();
		}
		return $this->owner->Link();
	}
}
?>