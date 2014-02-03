
<?php
include("topper.php");
require_once("initvars.inc.php");
require_once("config.inc.php");
require_once("pager.cls.php");
$qurl = curPageURL();
if(strpos($qurl, "page"))
    header("location:http://krispydeals.com/$xcityid/posts/12_Electronics/53_Other/");
// Pager
$page = $_GET['page'] ? $_GET['page'] : 1;
$offset = ($page-1) * $ads_per_page;

if ($sef_urls && !$xsearchmode)
{
    if ($xview == "events")
	{
		$urlformat = "{$vbasedir}$xcityid/events/$xdate/page{@PAGE}.html";
	}
	else
	{
		$urlformat = "{$vbasedir}$xcityid/posts/$xcatid/".($xsubcatid?$xsubcatid:0)."/page{@PAGE}.html";
	}
}
else
{
	$urlformat = "?";
	$tmp = $_GET;
	unset($tmp['page'], $tmp['msg']);
	foreach ($tmp as $k=>$v) $urlformat .= "$k=$v&";
	$urlformat .= "page={@PAGE}";
}


// Location condition
if($xcityid > 0)
{
	$loc_condn = $city_condn = "AND a.cityid = $xcityid";
}
else
{
	$loc_condn = $country_condn = "AND ct.countryid = $xcountryid";
}


if ($xview == "events")
{
	$where = "";

	if ($xsearch)
	{
		$searchsql = mysql_escape_string($xsearch);
		$where = "(a.adtitle LIKE '%$searchsql%' OR a.addesc LIKE '%$searchsql%') AND a.endon >= NOW()";
	}
	else if ($xdate)
	{
		$where = "(starton <= '$xdate' AND endon >= '$xdate')";
	}
	else
	{
		$where = "starton >= NOW()";
	}

	if($_GET['area']) $where .= "AND a.area = '$_GET[area]'";

	
	if ($xsearchmode)
	{
		$sort = "a.starton ASC";
	}
	else
	{
		$sort = "a.starton DESC";
	}


	// Get count
	$sql = "SELECT COUNT(*) AS adcount
			FROM $t_events a
				INNER JOIN $t_cities ct ON a.cityid = ct.cityid
				LEFT OUTER JOIN $t_featured feat ON a.adid = feat.adid AND feat.adtype = 'E'
			WHERE $where
				AND $visibility_condn
				AND (feat.adid IS NULL OR feat.featuredtill < NOW())
				$loc_condn";
	$tmp = mysql_query($sql) or die($sql.mysql_error());
	list($adcount) = mysql_fetch_array($tmp);
    $adcount = 20;
	// Get results
	$sql = "SELECT a.*, COUNT(*) AS piccount, p.picfile,
				UNIX_TIMESTAMP(a.createdon) AS timestamp, ct.cityname,
				UNIX_TIMESTAMP(a.starton) AS starton, UNIX_TIMESTAMP(a.endon) AS endon			
			FROM $t_events a
				INNER JOIN $t_cities ct ON a.cityid = ct.cityid
				LEFT OUTER JOIN $t_adpics p ON a.adid = p.adid AND p.isevent = '1'
				LEFT OUTER JOIN $t_featured feat ON a.adid = feat.adid AND feat.adtype = 'E'
			WHERE $where
				AND $visibility_condn
				AND (feat.adid IS NULL OR feat.featuredtill < NOW())
				$loc_condn
			GROUP BY a.adid
			ORDER BY $sort
			LIMIT $offset, $ads_per_page";
	$res = mysql_query($sql) or die($sql.mysql_error());

	// Get featured events
	$sql = "SELECT a.*, COUNT(*) AS piccount, p.picfile,
				UNIX_TIMESTAMP(a.createdon) AS timestamp, ct.cityname,
				UNIX_TIMESTAMP(a.starton) AS starton, UNIX_TIMESTAMP(a.endon) AS endon
			FROM $t_events a
				INNER JOIN $t_featured feat ON a.adid = feat.adid AND feat.adtype = 'E' AND feat.featuredtill >= NOW()
				INNER JOIN $t_cities ct ON a.cityid = ct.cityid
				LEFT OUTER JOIN $t_adpics p ON a.adid = p.adid AND p.isevent = '1'
			WHERE $where
				AND $visibility_condn
				$loc_condn
			GROUP BY a.adid
			ORDER BY $sort";
	$featres = mysql_query($sql) or die(mysql_error().$sql);
	
	// Vars
	$adtable = $t_events;
	$adtype = "E";
	$target_view = "showevent";
	$target_view_sef = "events";
	//$page_title = "Events";
	if ($_GET['date']) $link_extra = "&amp;date=$xdate";
	else $find_date = TRUE;

}
else
{
	// Make up the sql query
	$whereA = array();

	if ($xsearch)
	{
		$searchsql = mysql_escape_string($xsearch);
		$whereA[] = "(a.adtitle LIKE '%$searchsql%' OR a.addesc LIKE '%$searchsql%')";
	}

	if($_GET['area']) $whereA[] = "a.area = '$_GET[area]'";

	if ($xsubcathasprice && $_GET['pricemin'])
	{
		$whereA[] = "a.price >= $_GET[pricemin]";
	}

	if ($xsubcathasprice && $_GET['pricemax'])
	{
		$whereA[] = "a.price <= $_GET[pricemax]";
	}

	if ($xsubcatid)		$whereA[] = "a.subcatid = $xsubcatid";
	else if ($xcatid)	$whereA[] = "scat.catid = $xcatid";

	if (count($_GET['x']))
	{
		foreach ($_GET['x'] as $fldnum=>$val)
		{
			// Ensure numbers
			$fldnum += 0;
			if (!$val || !$fldnum) continue;

			if($xsubcatfields[$fldnum]['TYPE'] == "N" && is_array($val))
			{
				numerize($val['min']); numerize($val['max']);	// Sanitize
				if($val['min']) $whereA[] = "axf.f{$fldnum} >= $val[min]";
				if($val['max']) $whereA[] = "axf.f{$fldnum} <= $val[max]";
			}
			elseif($xsubcatfields[$fldnum]['TYPE'] == "D") 
			{
				$whereA[] = "axf.f{$fldnum} = '$val'";
			}
			else
			{
				$whereA[] = "axf.f{$fldnum} LIKE '%$val%'";
			}
		}
	}

	$where = implode(" AND ", $whereA);
	if (!$where) $where = "1";

	// Get count
	$sql = "SELECT COUNT(*) AS adcount
			FROM $t_ads a
				INNER JOIN $t_cities ct ON a.cityid = ct.cityid
				INNER JOIN $t_subcats scat ON a.subcatid = scat.subcatid
				INNER JOIN $t_cats cat ON scat.catid = cat.catid
				LEFT OUTER JOIN $t_adxfields axf ON a.adid = axf.adid
				LEFT OUTER JOIN $t_featured feat ON a.adid = feat.adid AND feat.adtype = 'A'
			WHERE $where
				AND $visibility_condn
				AND (feat.adid IS NULL OR feat.featuredtill < NOW())
				$loc_condn";

	$tmp = mysql_query($sql) or die(mysql_error());
	list($adcount) = mysql_fetch_array($tmp);

	// List of extra fields
	$xfieldsql = "";
	if(count($xsubcatfields)) 
	{
		for($i=1; $i<=$xfields_count; $i++)	$xfieldsql .= ", axf.f$i";
	}

	// Get results
	$sql = "SELECT a.*, UNIX_TIMESTAMP(a.createdon) AS timestamp, ct.cityname,
				COUNT(*) AS piccount, p.picfile,
				scat.subcatname, cat.catid, cat.catname $xfieldsql
			FROM $t_ads a
				INNER JOIN $t_cities ct ON a.cityid = ct.cityid
				INNER JOIN $t_subcats scat ON a.subcatid = scat.subcatid
				INNER JOIN $t_cats cat ON scat.catid = cat.catid
				LEFT OUTER JOIN $t_adxfields axf ON a.adid = axf.adid
				LEFT OUTER JOIN $t_adpics p ON a.adid = p.adid AND p.isevent = '0'
				LEFT OUTER JOIN $t_featured feat ON a.adid = feat.adid AND feat.adtype = 'A'
			WHERE $where
				AND $visibility_condn
				AND (feat.adid IS NULL OR feat.featuredtill < NOW())
				$loc_condn
			GROUP BY a.adid
			ORDER BY a.createdon DESC
			LIMIT $offset, $ads_per_page";
	$res = mysql_query($sql) or die($sql.mysql_error());

	// Get featured ads
	$sql = "SELECT a.*, UNIX_TIMESTAMP(a.createdon) AS timestamp, ct.cityname,
				COUNT(*) AS piccount, p.picfile,
				scat.subcatname, cat.catid, cat.catname $xfieldsql
			FROM $t_ads a
				INNER JOIN $t_featured feat ON a.adid = feat.adid AND feat.adtype = 'A' AND feat.featuredtill >= NOW()
				INNER JOIN $t_cities ct ON a.cityid = ct.cityid
				INNER JOIN $t_subcats scat ON a.subcatid = scat.subcatid
				INNER JOIN $t_cats cat ON scat.catid = cat.catid
				LEFT OUTER JOIN $t_adxfields axf ON a.adid = axf.adid
				LEFT OUTER JOIN $t_adpics p ON a.adid = p.adid AND p.isevent = '0'
			WHERE $where
				AND $visibility_condn
				$loc_condn
			GROUP BY a.adid
			ORDER BY feat.timestamp DESC";
	$featres = mysql_query($sql) or die(mysql_error().$sql);
	$featadcount = mysql_num_rows($featres);

	// Vars
	$adtable = $t_ads;
	$adtype = "A";
	$target_view = "showad"; 
	$target_view_sef = "posts"; 
	//$page_title = ($xsubcatname ? $xsubcatname : $xcatname);

}

$pager = new pager($urlformat, $adcount, $ads_per_page, $page);

$catimgsrcleft = "";
if($xcatname === "Math")
        $catimgsrcleft = "images/Math.png";
if($xcatname === "Science")
        $catimgsrcleft = "images/Science.png";
if($xcatname === "Social Studies")
        $catimgsrcleft = "images/Geography.png";
?>



<?php

if ($xview == "events" && !$xsearchmode)
{
	// Calendar navigation
	$prevmonth = mktime(0, 0, 0, $xdate_m-1, $xdate_d, $xdate_y);
	$nextmonth = mktime(0, 0, 0, $xdate_m+1, $xdate_d, $xdate_y);
	$prevday = $xdatestamp - 24*60*60;
	$nextday = $xdatestamp + 24*60*60;
?>
<table width="100%" class="eventnav" border="0"><tr>

<td valign="bottom">
<?php 
if($sef_urls)
{
	$prevday_url = "{$vbasedir}$xcityid/events/".date("Y-m-d", $prevday)."/";
	$nextday_url = "{$vbasedir}$xcityid/events/".date("Y-m-d", $nextday)."/";
}
else
{
	$prevday_url = "?view=events&date=".date("Y-m-d", $prevday)."&cityid=$xcityid&lang=$xlang";
	$nextday_url = "?view=events&date=".date("Y-m-d", $nextday)."&cityid=$xcityid&lang=$xlang";
}
?>
<a href="<?php echo $prevday_url; ?>">
<?php echo $lang['EVENTS_PREVDAY']; ?></a>
</td>

<td align="center">
<b><?php echo QuickDate($xdatestamp, FALSE, FALSE); ?> </b>
</td>

<td align="right" valign="bottom">
<a href="<?php echo $nextday_url; ?>">
<?php echo $lang['EVENTS_NEXTDAY']; ?></a>
</td>

</tr></table>
<?php
}
?>

<?php 
if(false or !$show_sidebar_always)
{
?>

	<div id="search_top">
	<b><?php echo $lang['SEARCH']; ?></b><br>
	<?php include("search.inc.php"); ?>
	</div>

<?php
}
?>


<?php

if ($adcount || mysql_num_rows($featres)>0)
{

?>


<table border="0" cellspacing="0" cellpadding="0" width="100%" class="adlisting">

<?php

if($xview == "events")
{

?>
<tr class="head">
<td>okokok<?php echo $lang['EVENTLIST_EVENTTITLE']; ?></td>
<td align="center" width="15%"><?php echo $lang['EVENTLIST_STARTSON']; ?></td>
<td align="center" width="15%"><?php echo $lang['EVENTLIST_ENDSON']; ?></td>
</tr>

<?php

// Featured events
if (mysql_num_rows($featres)>0)
{

	$css_first = "_first";

	while($row=mysql_fetch_array($featres))
	{
		if ($find_date) 
		{
			$link_extra = "&date=".date("Y-m-d", $row['starton']);
			$urldate = date("Y-m-d", $row['starton']);
		}

		if($sef_urls) $url = "{$vbasedir}$xcityid/$target_view_sef/$urldate/$row[adid]_" . RemoveBadURLChars($row['adtitle']) . ".html";
		else $url = "?view=$target_view&adid=$row[adid]&cityid=$xcityid&lang=$xlang{$link_extra}";

?>

		<tr class="featuredad<?php echo $css_first; ?>">
			<td>
			<a href="<?php echo $url; ?>" class="adtitle">
			<?php 
			if($row['picfile'] && $ad_thumbnails) 
			{ 
				$imgsize = GetThumbnailSize("{$datadir[adpics]}/{$row[picfile]}", $tinythumb_max_width, $tinythumb_max_height);
			?>
				<img src="<?php echo '$datadir[adpics]/$row[picfile]'; ?>" border="0" width="<?php echo $imgsize[0]; ?>" height="<?php echo $imgsize[1]; ?>" align="left" style="border:1px solid black;margin-right:5px;"> 
			<?php 
			}
			?>
			<img src="images/featured.gif" align="absmiddle" border="0">
			
			<?php echo $row['adtitle']; ?></a>

			<?php 
			$loc = "";
			if($row['area']) $loc = $row['area'];
			if($xcityid < 0) $loc .= ($loc ? ", " : "") . $row['cityname'];
			if($loc) echo "($loc)";
			?>

			<?php if($row['picfile']) { ?><img src="images/adwithpic.gif" align="absmiddle" title="This ad has picture(s)"><?php } ?>

						
			<?php 
			if($ad_preview_chars) 
			{ 
				echo "<span class='adpreview'>";
				$row['addesc'] = preg_replace("/\[\/?URL\]/", "", $row['addesc']);
				echo substr($row['addesc'],0,$ad_preview_chars);
				if (strlen($row['addesc'])>$ad_preview_chars) echo "...";
				echo "</span>";
			} 
			?>


			</td>
			<td align="center"><?php echo $langx['months_short'][date("n", $row['starton'])-1] . " " . date("j", $row['starton']); ?></td>
			<td align="center"><?php if($row['starton'] != $row['endon']) echo $langx['months_short'][date("n", $row['endon'])-1] . " " . date("j", $row['endon']);	?>

			
			</td>

		</tr>

<?php

		$css_first = "";
	}

}

?>
lkjh

<?php

	$i = 0;
	while($row=mysql_fetch_array($res))
	{
		$css_class = "ad" . (($i%2)+1);
		$i++;

	?>

		<tr class="<?php echo $css_class; ?>">

			<td>
				
				<?php

				if ($find_date) 
				{
					$link_extra = "&date=".date("Y-m-d", $row['starton']);
					$urldate = date("Y-m-d", $row['starton']);
				}

				if($sef_urls) $url = "{$vbasedir}$xcityid/$target_view_sef/$urldate/$row[adid]_" . RemoveBadURLChars($row['adtitle']) . ".html";
				else $url = "?view=$target_view&adid=$row[adid]&cityid=$xcityid&lang=$xlang{$link_extra}";


				?>

				<a href="<?php echo $url; ?>" class="adtitle">


				<?php 
				if($row['picfile'] && $ad_thumbnails) 
				{ 
					$imgsize = GetThumbnailSize("{$datadir[adpics]}/{$row[picfile]}", $tinythumb_max_width, $tinythumb_max_height);
				?>
					<img src="<?php echo "$datadir[adpics]/$row[picfile]"; ?>" border="0" width="<?php echo $imgsize[0]; ?>" height="<?php echo $imgsize[1]; ?>" align="left" style="border:1px solid black;margin-right:5px;"> 
				<?php 
				}
				?>


				<?php echo $row['adtitle']; ?></a>

				<?php 
				$loc = "";
				if($row['area']) $loc = $row['area'];
				if($xcityid < 0) $loc .= ($loc ? ", " : "") . $row['cityname'];
				if($loc) echo "($loc)";
				?>


				<?php if($row['picfile']) echo "<img src=\"images/adwithpic.gif\" align=\"absmiddle\" title=\"This ad has picture(s)\"> "; ?>

				
							
			<?php 
			if($ad_preview_chars) 
			{ 
				echo "<span class='adpreview'>";
				$row['addesc'] = preg_replace("/\[\/?URL\]/", "", $row['addesc']);
				echo substr($row['addesc'],0,$ad_preview_chars);
				if (strlen($row['addesc'])>$ad_preview_chars) echo "...";
				echo "</span>";
			} 
			?>


			</td>
			<td align="center"><?php echo $langx['months_short'][date("n", $row['starton'])-1] . " " . date("j", $row['starton']); ?></td>
			<td align="center"><?php if($row['starton'] != $row['endon']) echo $langx['months_short'][date("n", $row['endon'])-1] . " " . date("j", $row['endon']);	?>

				
				</td>

		</tr>

	<?php

	}
}
else
{

?>
<tr class="head">
<td height="1%"></td></tr>
<tr>
<td><?php /*echo $lang['ADLIST_ADTITLE'];*/ ?>
        <h1>Ads in <?php echo $xcityname; ?>:</h1>
<h2>For more ads, choose a location on the left sidebar!</h2>
</td>
<?php
$colspan = 1;
foreach ($xsubcatfields as $fldnum=>$fld)
{
	if (!$fld['SHOWINLIST']) continue;

	echo "<td";
	//if ($fld['TYPE']=="N") 
	echo " align=\"center\"";
	echo ">$fld[NAME]</td>";
	$colspan++;
}
if ($xsubcathasprice) 
{
	echo "<td align=\"right\" width=\"12%\">$xsubcatpricelabel</td>";
	$colspan++;
}
?>
</tr>

<?php

// Featured ads
if (mysql_num_rows($featres)>0)
{
	
	echo "<tr><td height=\"1\"></td></tr>";
	$css_first = "_first";

	while($row=mysql_fetch_array($featres))
	{

		$catname_inurl = RemoveBadURLChars($row['catname']);
		$subcatname_inurl = RemoveBadURLChars($row['subcatname']);

		if($sef_urls) $url = "{$vbasedir}$xcityid/$target_view_sef/{$row[catid]}_{$catname_inurl}/{$row[subcatid]}_{$subcatname_inurl}/$row[adid]_" . RemoveBadURLChars($row['adtitle']) . ".html";
		else $url = "?view=$target_view&adid=$row[adid]&cityid=$xcityid&lang=$xlang{$link_extra}";

?>

		<tr class="featuredad<?php echo $css_first; ?>">
			<td>
			<a href="<?php echo $url; ?>" class="adtitle">


			<?php 
			if($row['picfile'] && $ad_thumbnails) 
			{ 
				$imgsize = GetThumbnailSize("{$datadir[adpics]}/{$row[picfile]}", $tinythumb_max_width, $tinythumb_max_height);
			?>
				<img src="<?php echo "$datadir[adpics]/$row[picfile]"; ?>" border="0" width="<?php echo $imgsize[0]; ?>" height="<?php echo $imgsize[1]; ?>" align="left" style="border:1px solid black;margin-right:5px;"> 
			<?php 
			}
			?>



			<img src="images/featured.gif" align="absmiddle" style="padding-right:5px;" border="0"><?php echo $row['adtitle']; ?></a>
			<?php 
			$loc = "";
			if($row['area']) $loc = $row['area'];
			if($xcityid < 0) $loc .= ($loc ? ", " : "") . $row['cityname'];
			if($loc) echo "($loc)";
			?>
			<?php if($row['picfile']) { ?><img src="images/adwithpic.gif" align="absmiddle" title="This ad has Picture(s)"><?php } ?>

							
				<?php 
				if($ad_preview_chars) 
				{ 
					echo "<span class='adpreview'>";
					$row['addesc'] = preg_replace("/\[\/?URL\]/", "", $row['addesc']);
					echo substr($row['addesc'],0,$ad_preview_chars);
					if (strlen($row['addesc'])>$ad_preview_chars) echo "...";
					echo "</span>";
				} 
				?>


			</td>

			<?php

			foreach ($xsubcatfields as $fldnum=>$fld)
			{
				if (!$fld['SHOWINLIST']) continue;

				echo "<td";
				//if ($fld['TYPE']=="N")
				echo " align=\"center\"";
				echo "width=\"10%\">&nbsp;".
					((($fld['TYPE']=="N" && ($row["f$fldnum"]==-1 || $row["f$fldnum"]=="0" || $row["f$fldnum"]=="")) || ($fld['TYPE']!="N" && trim($row["f$fldnum"])==""))?"-":$row["f$fldnum"])."</td>";
			}

			if($xsubcathasprice) 
				echo "<td align=\"right\">&nbsp;".($row['price'] > 0.00?"$currency".$row['price']:"-")."</td>";
			
			?>

		</tr>

<?php

		$css_first = "";
	
	}

}

?>
<?php

	$i = $j = 0;
	$lastdate = "";
	while($row=mysql_fetch_array($res))
	{
		$date_formatted = date("Ymd", $row['timestamp']);
		if($date_formatted != $lastdate)
		{
			if ($lastdate) 
			{
				//echo "<tr><td height=\"1\"></td></tr>";
				$j = 0;
			}

			echo "<tr><td height=\"1\"></td></tr><tr><td class=\"datehead\" colspan=\"$colspan\">".QuickDate($row['timestamp'], FALSE, FALSE)."</td></tr><tr><td height=\"1\"></td></tr>";

			$lastdate = $date_formatted;
		}

		$css_class = "ad" . (($j%2)+1);
		$i++; $j++;

		$catname_inurl = RemoveBadURLChars($row['catname']);
		$subcatname_inurl = RemoveBadURLChars($row['subcatname']);

		if($sef_urls) $url = "{$vbasedir}$xcityid/$target_view_sef/{$row[catid]}_{$catname_inurl}/{$row[subcatid]}_{$subcatname_inurl}/$row[adid]_" . RemoveBadURLChars($row['adtitle']) . ".html";
		else $url = "?view=$target_view&adid=$row[adid]&cityid=$xcityid&lang=$xlang{$link_extra}";

		if($xsearchmode || !$xsubcatid)
		{
			if($sef_urls) $subcatlink = "{$vbasedir}$xcityid/$target_view_sef/{$row[catid]}_{$catname_inurl}/{$row[subcatid]}_{$subcatname_inurl}/";
			else $subcatlink = "?view=ads&subcatid=$row[subcatid]&cityid=$xcityid&lang=$xlang{$link_extra}";

			$title_extra = "&nbsp;- <a href=\"$subcatlink\" class=\"adcat\">$row[catname] $path_sep $row[subcatname]</a>";
		}


	?>

		<tr class="<?php echo $css_class; ?>">

			<td>
				
				<a href="<?php echo $url; ?>" class="adtitle">
				<?php 
				if($row['picfile'] && $ad_thumbnails && !in_array($row['picfile']['type'], $pic_filetypes)) 
				{ 
					$imgsize = GetThumbnailSize("{$datadir[adpics]}/{$row[picfile]}", $tinythumb_max_width, $tinythumb_max_height);
				?>
					<img src="<?php echo "$datadir[adpics]/$row[picfile]"; ?>" border="0" width="20%" align="left" class="adpicsleft"> 
				<?php 
				}
                else
                {
                    
                ?>
                    <img src= <?php echo "'images/no-image.png'"; ?> border="0" width="20%" align="left" class="adpicsleft"> 
            <?php    }
				?>


				<?php echo $row['adtitle']; ?></a>
				<?php 
				$loc = "";
				if($row['area']) $loc = $row['area'];
				if($xcityid < 0) $loc .= ($loc ? ", " : "") . $row['cityname'];
				if($loc) echo "($loc)";
				?>

				<?php if($row['picfile']) echo "<img src=\"images/adwithpic.gif\" align=\"absmiddle\" title=\"This ad has picture(s)\"> "; ?>
				<?php echo $title_extra; ?>

				
				<?php 
				if($ad_preview_chars) 
				{ 
					echo "<span class='adpreview'>";
					$row['addesc'] = preg_replace("/\[\/?URL\]/", "", $row['addesc']);
					echo substr($row['addesc'],0,$ad_preview_chars);
					if (strlen($row['addesc'])>$ad_preview_chars) echo "...";
					echo "</span>";
				} 
				?>


			</td>
			<?php

			foreach ($xsubcatfields as $fldnum=>$fld)
			{
				if (!$fld['SHOWINLIST']) continue;

				echo "<td";
				//if ($fld['TYPE']=="N")
				echo " align=\"center\"";
				echo "width=\"10%\">&nbsp;".
					((($fld['TYPE']=="N" && ($row["f$fldnum"]==-1 || $row["f$fldnum"]=="0" || $row["f$fldnum"]=="")) || ($fld['TYPE']!="N" && trim($row["f$fldnum"])==""))?"-":$row["f$fldnum"])."</td>";
			}

			if($xsubcathasprice) 
				echo "<td align=\"right\">&nbsp;".($row['price'] > 0.00?"$currency".$row['price']:"-")."</td>";
			
			?>

		</tr>

	<?php

	}
}
?>

</table>

<?php

if ($adcount > $ads_per_page)
{

?>

<br>
<div align="right">
<table>
<tr><td><b><?php echo $lang['PAGE']; ?>: </b></td><td><a href='<?php echo "http://www.krispydeals.com/$xcityid/posts/12/53/page2.html" ?>' >Next Page</a></td></tr>
</table>
</div>

<?php
}

?>


<?php

}
else
{

?>

<div class="noresults"><?php echo $lang['NO_RESULTS']; 

$strstart = strpos($qurl, "search")+7;
$strfin = strpos($qurl, "&subcatid");
$quer = substr($qurl, $strstart, $strfin - $strstart);
    
    //echo "<br><iframe height='1000' width='60%' src=\"grabber/index.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    /*echo "<br><iframe height='500' src=\"grabber/atlanta.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/chicago.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/newyork.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/lasvegas.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/orlando.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/miami.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/houston.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/elpaso.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/brownsville.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/sfbay.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/fresno.php?subcat=$xsubcatid&city=sandiego&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/fresno.php?subcat=$xsubcatid&city=sacramento&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/fresno.php?subcat=$xsubcatid&city=losangeles&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/fresno.php?subcat=$xsubcatid&city=fresno&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/seattle.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/spokane.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/denver.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";
    echo "<br><iframe height='500' src=\"grabber/wasdc.php?subcat=$xsubcatid&city=$xcityid&query=$xsearch\"></iframe>";*/
?>

<form name="myForm" action="http://google.com" method="post">
<fieldset><h2><legend>Type or say the following information:</legend></h2>
<select id='bandlist'>
    <option value="Dallas">Dallas</option>
    <option value="San Antonio">San Antonio</option>
</select>
<input type="submit" value="Submit">
</fieldset>
</form>
<script>
document.myForm.onsubmit = function(){
    alert("hi");
};

</script>
<h2>Prepare to be redirected!</h2>
<?php

echo $url;

?>
 <br>
<a href="?view=main&cityid=<?php echo $xcityid; ?>"><?php echo $lang['BACK_TO_HOME']; ?></a>
</div>

<?php

}

?>
