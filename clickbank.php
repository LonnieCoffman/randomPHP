<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
	<label for="file">Filename:</label>
	<input type="file" name="file" id="file" />
	<input type="submit" name="submit" value="Submit" />
</form>

<?php
// IF FILE EXISTS CONTINUE
if ((isset($_POST['submit'])) && ($_FILES['file']['tmp_name'] != '')){

	$xml = simplexml_load_file($_FILES['file']['tmp_name']);
	
	foreach($xml->Category as $category){
		foreach($category->Site as $site){
			if (($site->Gravity >= 50) && ($site->EarnedPerSale >= 30)){
				$potential[(string)$site->Id]['id'] = (string)$site->Id;
				$potential[(string)$site->Id]['title'] = (string)$site->Title;
				$potential[(string)$site->Id]['description'] = (string)$site->Description;
				$potential[(string)$site->Id]['category'] = (string)$category->Name;
				$potential[(string)$site->Id]['gravity'] = (string)$site->Gravity;
				$potential[(string)$site->Id]['payout'] = (string)$site->EarnedPerSale;
				$potential[(string)$site->Id]['link'] = 'http://zzzzz.'.strtolower((string)$site->Id).'.hop.clickbank.net/';
			}
		}
		foreach($category->Category as $subcategory){
			foreach($subcategory->Site as $subsite){
			if (($subsite->Gravity >= 50) && ($subsite->EarnedPerSale >= 30)){
				$potential[(string)$subsite->Id]['id'] = (string)$subsite->Id;
				$potential[(string)$subsite->Id]['title'] = (string)$subsite->Title;
				$potential[(string)$subsite->Id]['description'] = (string)$subsite->Description;
				$potential[(string)$subsite->Id]['subcategory'] = (string)$subcategory->Name;
				$potential[(string)$subsite->Id]['gravity'] = (string)$subsite->Gravity;
				$potential[(string)$subsite->Id]['payout'] = (string)$subsite->EarnedPerSale;
				$potential[(string)$subsite->Id]['link'] = 'http://zzzzz.'.strtolower((string)$subsite->Id).'.hop.clickbank.net/';
			}
			}
		}
	}
	
	echo 'Total: '.count($potential).'<br />';
	
	foreach ($potential as $listing){
		echo '<br />';
		echo '<a href="'.$listing['link'].'" target="_blank">'.$listing['title'].'</a><br />';
		echo $listing['description'].'<br />';
		echo 'Gravity: '.$listing['gravity'].' | Payout: '.$listing['payout'].'<br />';
	}
	
//	echo '<pre>';
//	print_r($potential);
//	echo '</pre>';

}
?>