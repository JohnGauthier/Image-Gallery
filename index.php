<?php

class gallery {

	/* ***** ADJUSTABLE GLOBAL VARIABLES ***** */

	private $_strTitle = "John G. Gauthier's Gallery";	// Title for the gallery.
	private $_intThumbSize = 200; 						// Maximum width and height in pixels.
	private $_intThumbMargin = 3;						// Number of pixels between the thumbnail and the border.
	private $_strThumbDir = "_thumbs/";				// MUST end in '/', eg: "_thumbs/";

	/* *************************************** */
	/* DO NOT MODIFY THE CODE BELOW THIS POINT */
	/* *************************************** */

	private $_timeStart = 0;

	public function __construct() {
		$this->_timeStart = microtime(true);

		define('DS', DIRECTORY_SEPARATOR);
		define('URL_ENTIRE', $this->fetchEntireURL());
		define('URL_BASE', $this->fetchBaseURL());

		$this->checkRootViewAttempt();
		$this->checkClearThumbnailRequest();

		// If the thumbnail directory doesn't exist, create it.
		$strSubDirectory = (isset($_GET['d']))
			? "{$_GET['d']}/"
			: "";
		if (!is_dir($this->_strThumbDir . $strSubDirectory)) {
			mkdir ($this->_strThumbDir . $strSubDirectory, 0755, true);
		}

		$this->generateHeader();
		$this->buildAll($strSubDirectory);
		$this->generateFooter();
	}

	/*
	 * Loops through every directory, then every file, in the target folder and creates the necessary HTML structures.
	 */
	private function buildAll($strSubDirectory) {
		// Cycle through the contents of the folder, splitting the results into an array of directories (to be listed first) and an array of files (to be shown second)
		$fhDir = dir(getcwd() . DS . $strSubDirectory);
		while ($strItem = $fhDir->read()) {
			if (is_dir(getcwd() . DS . $strSubDirectory . DS . $strItem)) {
				// Check that this is a valid directory to include
				if ((($strItem != ".") && ($strItem != "..") && (substr($strItem,0,1) != '_')) || ($strItem == ".." && $strSubDirectory != "")) {
					$arrDirs[count($arrDirs)] = $strItem;
				}
			} else if (!ereg(".php",strtolower($strItem)) && !ereg("thumbs.db",strtolower($strItem))) {
				$arrFiles[count($arrFiles)] = $strItem;
			}
		}
		$fhDir->close();

		if (is_array($arrDirs))  sort($arrDirs);
		if (is_array($arrFiles)) sort($arrFiles);

		for ($i = 0; $i < count($arrDirs); $i++) {
			echo($this->buildDivForDir($strSubDirectory . $arrDirs[$i]));
		}

		for ($i = 0; $i < count($arrFiles); $i++) {
			/* Determine what kind of thumbnail we need to build. */
			if (ereg(".jpg",strtolower($arrFiles[$i])) ||
				ereg(".tif",strtolower($arrFiles[$i])) ||
				ereg(".gif",strtolower($arrFiles[$i])) ||
				ereg(".png",strtolower($arrFiles[$i])))
			{
				echo($this->buildDivForImage($strSubDirectory . $arrFiles[$i]));
			} else {
				echo($this->buildDivForOther($strSubDirectory . $arrFiles[$i]));
			}
		}
	}

	/*
	 * Build a div for a directory.
	 */
	private function buildDivForDir($strFilename) {
		// build a user-friendly file name.
		$strName = str_replace($_GET['d'], "", $strFilename);
		// if we are in the root directory, preface the name with '/' for readability.
		if ($_GET['d']=="") {
			$strName="/$strName";
		}
		// If the root is our parent, point at the base URL.
		$strURL = URL_BASE;
		if (strpos($strFilename,"..")>0) {
			// Replace name for clarification for non-DOS fluent folk.
			$strName = "Return to parent directory...";
			// Handle depth issues.
			// If we are deeper than one child, build the new URL.
			if ($strFilename != "..") {
				// Remove '/..' from the filename
				$strURL .= "?d=".substr($strFilename, 0, -3);
				// Remove one directory layer from the right
				$strURL = substr($strURL, 0, strrpos($strURL, "/"));
			}
		// Subdirectories
		} else {
			$strURL .= "?d={$strFilename}";
		}
		// Build the div to encapsulate the text.
		return "		<div class=\"outer\"><div class=\"front\" onclick=\"location.href='{$strURL}'\">{$strName}</div></div>\n";
	}

	/*
	 * Build a div for an image.
	 */
	private function buildDivForImage($strFilename) {
		// Build the div statement with thumbnail, with the div itself being a hyperlink to the full image
		return "		<div class=\"outer\"><div class=\"front\" onclick=\"location.href='{$strFilename}'\"><img style=\"border:0;\" src=\"{$this->buildThumb($strFilename)}\" alt=\"{$strFilename}\" title=\"{$this->formatFilename($strFilename)}\"></div></div>\n";
	}

	/*
	 * Build a div for a viewable/navigatable object other than a jpg image.
	 */
	private function buildDivForOther($strFilename) {
		// Build the div to encapsulate the text.
		return "		<div class=\"outer\"><div class=\"front\" onclick=\"location.href='{$strFilename}'\">{$this->formatFilename($strFilename)}</div></div>\n";
	}

	/*
	 * Construct a thumbnail image for an image file.
	 */
	private function buildThumb($strFilename) {
		$strThumbFilename = $this->_strThumbDir . substr($strFilename,0,(strlen($strFilename) - 4)) . substr($strFilename,(strlen($strFilename) - 4));

		// $arrDim[0] = width of the image in pixels.
		// $arrDim[1] = height of the image in pixels.
		// $arrDim[2] = flag indicating the type of the image:
		//    1 = GIF
		//    2 = JPG
		//    3 = PNG
		//    4 = SWF
		//    5 = PSD
		//    6 = BMP
		//    7 = TIFF(intel byte order)
		//    8 = TIFF(motorola byte order)
		//    9 = JPC
		//   10 = JP2
		//   11 = JPX
		//   12 = JB2
		//   13 = SWC
		//   14 = IFF

		// If the thumb exists AND it's the same size as what we're after, bail because we're done.
		if (file_exists($strThumbFilename)) {
			$arrThumbDim = getimagesize($strThumbFilename);
			if ($arrThumbDim[0] == $this->_intThumbSize || $arrThumbDim[1] == $this->_intThumbSize) {
				return $strThumbFilename;
			}
		}

		$arrDim = getimagesize($strFilename);

		// Get new sizes
		$intOrigRatio = $arrDim[0] / $arrDim[1];

		$intNewWidth = $this->_intThumbSize;
		$intNewHeight = $this->_intThumbSize;

		// Build new height, width
		if ($intNewWidth/$intNewHeight > $intOrigRatio) {
			$intNewWidth = $intNewHeight * $intOrigRatio;
		} else {
			$intNewHeight = $intNewWidth / $intOrigRatio;
		}

		// Load
		switch($arrDim[2]) {
			case 1: $objSource = imagecreatefromgif($strFilename); break;
			case 2: $objSource = imagecreatefromjpeg($strFilename); break;
			case 3: $objSource = imagecreatefrompng($strFilename); break;
		}

		if ($intNewWidth >= $arrDim[0] || $intNewHeight >= $arrDim[1]) {
			$intNewWidth = $arrDim[0];
			$intNewHeight = $arrDim[1];
		}

		// Resize
		$objThumb = imagecreatetruecolor($this->_intThumbSize, $this->_intThumbSize);
		imagefill($objThumb, 0, 0, imagecolorallocate($objThumb, 255, 255, 255));
		imagecopyresized($objThumb, $objSource, 0, 0, 0, 0, $intNewWidth, $intNewHeight, $arrDim[0], $arrDim[1]);

		// Save the thumbnail image
		switch($arrDim[2]) {
			case 1: imagegif($objThumb, $strThumbFilename); break;
			case 2: imagejpeg($objThumb, $strThumbFilename); break;
			case 3: imagepng($objThumb, $strThumbFilename); break;
		}

		// Clean up.
		imagedestroy($objSource);
		imagedestroy($objThumb);

		// Provide the parent function with the thumbnail file name.
		return $strThumbFilename;
	}

	/*
	 * Block attempts to view root file system.
	 *		Prevents a security exploit by ignoring all syntaxes for viewing a directory higher than web root.
	 */
	private function checkRootViewAttempt() {
		if ((substr($_GET['d'],0,1)=='.') || (substr($_GET['d'],0,1)=='/') || (substr($_GET['d'],0,1)=='\\')) {
			header("location: " . URL_BASE);
			exit();
		}
	}

	/*
	 * Destroys all thumbnails in a given directory.
	 *		If there is a problem with a thumbnail, the user has the ability to clear the thumbnails and cause them to be rebuilt on the next page load.
	 */
	private function checkClearThumbnailRequest() {
		// Per user request, clear existing thumbnails.
		if (isset($_GET['r'])) {
			$this->removeDirectory(getcwd() . DS . substr($this->_strThumbDir,0,-1) . DS . (isset($_GET['d']) ? $_GET['d'] : ""));
			header("location: " . URL_BASE);
			exit();
		}
	}

	/*
	 * Build a user-friendly file name.
	 */
	private function formatFilename($strFilename) {
		return str_replace("/", "", str_replace($_GET['d'], "", $strFilename));
	}

	private function generateFooter() {
?>
		<div class="clear"></div>
		<div class="w3c">
			<a href="http://validator.w3.org/check?uri=referer"><img src="http://www.w3.org/Icons/valid-html401-blue" alt="Valid HTML 4.01 Strict" height="31" width="88" style="border:0;"></a>
		</div>
		<div class="elapse">
			Page built in <?php echo(microtime(true) - $this->_timeStart); ?> seconds.<br>
			<i><a href="<?php echo(URL_ENTIRE . (isset($_GET['d']) ? "&amp;" : "?")); ?>r=1">Click here to rebuild the thumbnails above.</a></i>
		</div>
		<div class="clear"></div>
	</body>
</html>
<?php
	}

	private function generateHeader() {
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
		<style type="text/css">
			body {
				margin: 0px;
				padding: 0px;
			}
			div.title {
				font-size: 14px;
				font-weight: bold;
				margin: 9px;
			}
			div.outer {
				border: 1px solid #000;
				float: left;
				height: <?php echo($this->_intThumbSize + $this->_intThumbMargin*2); ?>px;
				margin: 5px;
				width: <?php echo($this->_intThumbSize + $this->_intThumbMargin*2); ?>px;
			}
			div.front {
				background-color: #FFF;
				cursor: pointer;
				height: <?php echo($this->_intThumbSize); ?>px;
				margin: <?php echo($this->_intThumbMargin); ?>px;
				width: <?php echo($this->_intThumbSize); ?>px;
			}
			div.clear {
				clear: both;
			}
			div.w3c {
				float: left;
				margin: 5px 10px;
			}
			div.elapse {
				float: right;
				font-size: 12px;
				margin-right: 2px;
				text-align: right;
			}
		</style>
		<title><?php echo($this->_strTitle); ?></title>
	</head>
	<body>
		<div class="title">
			<a href="<?php echo(URL_BASE); ?>"><?php echo($this->_strTitle); ?></a><?php echo((($_GET['d']) ? " - {$_GET['d']}" : "")); ?>
		</div>
<?php
	}

	/*
	 * Determine index.php's location in the site.
	 *		This will be used later to assemble hyperlinks and to prevent
	 *		navigation any higher up the tree than the starting point.
	 *
	 */
	private function fetchBaseURL() {
		// If our current URL contains a '?' then it and any PHP variables need to be pruned.
		return substr(URL_ENTIRE, 0, strpos(URL_ENTIRE, strstr(URL_ENTIRE,'?') ? '?' : 'index.php'));
	}

	/*
	 * Return the entire URL.
	 */
	private function fetchEntireURL() {
		$s = empty($_SERVER["HTTPS"])
			? ""
			: ($_SERVER["HTTPS"] == "on")
				? "s"
				: "";
	    $protocol = substr(strtolower($_SERVER["SERVER_PROTOCOL"]), 0, strpos(strtolower($_SERVER["SERVER_PROTOCOL"]), "/")).$s;
	    $port = ($_SERVER["SERVER_PORT"] == "80")
	    	? ""
	    	: (":".$_SERVER["SERVER_PORT"]);
		// Build the URL to return.
		$strURL = "{$protocol}://{$_SERVER["SERVER_NAME"]}{$port}{$_SERVER["REQUEST_URI"]}";
		if (!strstr($strURL,"index.php") && !strstr($strURL,'?')) {
			$strURL = "{$strURL}index.php";
		}
		return $strURL;
	}

	private function removeDirectory($strPath) {
        foreach(scandir($strPath) as $strFile){
            if ($strFile !== '.' && $strFile !== '..'){
                if (is_dir($strPath.'/'.$strFile)){
                    if (count(glob($strPath.'/'.$strFile.'/*')) > 0){
                        $this->removeDirectory($strPath.'/'.$strFile);
                    } else {
                        rmdir($strPath.'/'.$strFile);
                    }
                } else {
                    unlink($strPath.'/'.$strFile);
                }
            }
        }
        rmdir($strPath);
	}
};

new gallery();

