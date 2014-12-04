<?php
function doTemplate($htmlContent, $htmlTitle, $htmlFlashes, &$props, &$callback) {
	global $PATH_TO_FILES_ON_WEB, $FRONT_PAGE_LINK, $SITE_NAME, $DEFAULT_COPYRIGHT;
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style type="text/css">
		@import url(<?php echo($PATH_TO_FILES_ON_WEB);?>/template.css);
	</style>
	<title><?php echo($htmlTitle);?> &mdash; <?php echo($SITE_NAME);?></title>
</head>

<body>
	<table border="0" cellspacing="0" cellpadding="0" class="topMostTable" align="center">
	<tr><td>
		<table width="100%" border="0" cellspacing="0" cellpadding="0" class="topRowTable">
			<tr>
				<td class="topRow">
					<a href="<?php echo($FRONT_PAGE_LINK)?>" title="Home Page"><?php echo($SITE_NAME);?></a>
				</td>
			</tr>
			<tr class="topRow2">
				<td class="topRow2"></td>
			</tr>
		</table>
		<table width="100%" border="0" cellspacing="0" cellpadding="0" class="mainTable">
			<tr>
				<td class="leftColumn" valign="top" align="left">
					<div>
<?php echo($htmlFlashes);?>
<?php echo($htmlContent);?>
					</div>
<div class="copyright"><?php
	if (isset($props["copyright"])) echo($props["copyright"]); else { echo($DEFAULT_COPYRIGHT); } ?></div>

					<div id="forceLeftColumnWidth"></div>
				</td>
				<td class="rightColumn" valign="top">
					
					<div class="sideBarContent">
						<?php if (isset($props["frontPage"])) { ?>
							<?php echo($props["frontPage"]);?><br />
						<?php } ?>

						<?php
							$sidebar = $callback->request("sidebar");
							if ($sidebar !== null) {
								echo($sidebar);
							}
						?>

						<?php if (isset($props["viewThisPage"])) { ?>
							<?php echo($props["viewThisPage"]);?><br />
						<?php } ?>
						<?php if (isset($props["editThisPage"])) { ?>
							<?php echo($props["editThisPage"]);?><br />
						<?php } ?>
						<?php if (isset($props["fileManager"])) { ?>
							<?php echo($props["fileManager"]);?><br />
						<?php } ?>
						<?php if (isset($props["signInOut"])) { ?>
							<?php echo($props["signInOut"]);?><br />
						<?php } ?>
						<?php if (isset($props["userAdmin"])) { ?>
							<?php echo($props["userAdmin"]);?><br />
						<?php } ?>
						
					</div>

					<div id="forceRightColumnWidth"></div>
				</td>
			</tr>
		</table>
		<table width="100%" border="0" cellspacing="0" cellpadding="0" class="bottomTable">
			<tr class="bottomRow">
				<td class="bottomRow"></td>
			</tr>
		</table>
	</td>
</tr>
</table>
	
</body>
</html>
<?php
}
?>
