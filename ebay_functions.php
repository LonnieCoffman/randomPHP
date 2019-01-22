<?php

  //=================================================//
 // Convert Unix Time to Ebay Time. defaults to now.//
//=================================================//
function ebay_time( $timestamp=0 ){
	if ($timestamp == 0) $timestamp = time(); // default to now
	$format = "Y-m-d\TH:i:00.000\Z";
	return gmdate($format,$timestamp);
}

  //=================================//
 // Convert Ebay Time to Unix Time. //
//=================================//
function unix_time ( $ebay_time ){
	// strip not needed
	$ebay_time = str_replace('T', ' ', $ebay_time);
	$ebay_time = str_replace('.000Z', ' GMT', $ebay_time);
	$ebay_time = strtotime($ebay_time);
	return $ebay_time;
}

  //=================================================//
 // download xml files from ebay for given category //
//=================================================//
function fetch_xml_files( $category=0 ){

	if ($category == 0){
		$sql = "SELECT ebay_id, last_updated
							FROM ebay_categories
						 WHERE active = '1'
					ORDER BY last_updated, ebay_id
						 LIMIT 1";
	} else {
		$sql = "SELECT ebay_id, last_updated
							FROM ebay_categories
						 WHERE ebay_id = '{$category}'";
	}

	$query = mysql_query($sql) or die (mysql_error());
	$total_results = 0;
	$a = 0;
	$b = 0;
	$time = time();
	while($cat = mysql_fetch_assoc($query)){

		$ebay_id = $cat['ebay_id'];
		
		// AUD = australian dollars
		// CAD = canadian dollars
		// EUR = euros ( for belgium, spain, ireland )
		// GBP = pounds ( for UK )
		// HKD = hong kong dollar (for HK)
		// USD = us dollar
		// NOT EVERY CURRENCY IS COMPATIBLE (need to convert internally)
		$currency = 'AUD';
	
		// australia = 15
		// belgium = 123
		// canada = 2
		// hong kong = 201
		// ireland = 205
		// spain = 186
		// united kingdom = 3
		// united states = 0
		$country_code = '15';

		if ($cat['last_updated'] != 0){
			$ebay_time = ebay_time($cat['last_updated']);
			$modtimefrom = "&ModTimeFrom=$ebay_time";
		} else {
			$modtimefrom = '';
		}
	
		$endpoint = 'http://open.api.ebay.com/shopping';  // URL to call
		$responseEncoding = 'XML';   // Format of the response

		$error_array = array();
		$xml_files = array();

		// Construct the FindItems call 
		$apicall = $endpoint."?callname=FindItemsAdvanced"
		         . "&version=537"
		         . "&siteid=".$country_code
		         . "&currency=".$currency
		         . "&appid=LonnieCo-032e-41ae-8767-24ca6f810885"
		         . "&PriceMin.Value=5"
		         . "&CategoryID=".$ebay_id
		         . "&ItemSort=CurrentBid"
		         . "&SortOrder=Descending"
		         . "&ItemType=AllItemTypes"
		         . "&IncludeSelector=Details,SellerInfo,IncludePictureURL,ItemSpecifics"
		         . "&HideDuplicateItems=1"
	           . "&trackingpartnercode=9"
	           . "&trackingid=5336423527"
	           . $modtimefrom
		         . "&responseencoding=".$responseEncoding;

		// Get Page Count
		$xml = simplexml_load_file($apicall.'&MaxEntries=0');
		$total_results = (string)$xml->TotalItems;
	
		if ($total_results > 0){
			$total_pages = ceil($total_results / 100);
			$xml_files['results'][$a]['category'] = $ebay_id;
			$xml_files['results'][$a]['total'] = $total_results;
			$page_number = 1;
			while ($page_number <= $total_pages){
				$xml_files['files'][$b]['destination'] = 'xml_files/'.$time.'-'.$ebay_id.'-'.(($page_number < 10) ? '0': '').$page_number.'-'.$total_pages.'.xml';
				$xml_files['files'][$b]['source'] = $apicall.'&MaxEntries=100&PageNumber='.$page_number;
				$page_number++;
				$b++;
			}
		}
		$a++;
	}
 
 	if (!empty($xml_files)){
		$mh = curl_multi_init();
		foreach ($xml_files['files'] as $i => $file){
		    $g = $file['destination'];
		    if(!is_file($g)){
		        $conn[$i]=curl_init($file['source']);
		        $fp[$i]=fopen ($g, "w");
		        curl_setopt ($conn[$i], CURLOPT_FILE, $fp[$i]);
		        curl_setopt ($conn[$i], CURLOPT_HEADER ,0);
		        curl_setopt($conn[$i],CURLOPT_CONNECTTIMEOUT,60);
		        curl_multi_add_handle ($mh,$conn[$i]);
		    }
		} do {
			$n=curl_multi_exec($mh,$active);
		}
		while ($active);
		foreach ($xml_files['files'] as $i => $file){
	    curl_multi_remove_handle($mh,$conn[$i]);
	    curl_close($conn[$i]);
	    fclose ($fp[$i]);
		}
		curl_multi_close($mh);
	}
	
	$sql = "UPDATE ebay_categories SET last_updated = '".$time."', raw_results = '".$total_results."' WHERE ebay_id = '".$ebay_id."'";
	$query = mysql_query($sql) or die (mysql_error());

	return;
}

  //===================================//
 // parse downloaded XML files if any //
//===================================//
function parse_xml_files( $ebay_category=0 ){
	
	sweep_listings(); // remove expired listings before updating
	
	// read directory and place values in array.
	$directory = 'xml_files';
  $data = array();

  $handler = opendir($directory);
	$a = 0;

  while ($file = readdir($handler)) {
		if ($file != '.' && $file != '..'){
			if ($a < 20){
				$raw = explode('-', $file);
				if ($ebay_category == 0){
					$data[$a]['time'] = $raw[0];
					$data[$a]['category'] = $raw[1];
					$data[$a]['current_page'] = (int)$raw[2];
					$data[$a]['total_pages'] = (int)$raw[3];
					$data[$a]['filename'] = 'xml_files/'.$file;
					$a++;
				} else {
					if ($ebay_category == $raw[1]){
						$data[$a]['time'] = $raw[0];
						$data[$a]['category'] = $raw[1];
						$data[$a]['current_page'] = (int)$raw[2];
						$data[$a]['total_pages'] = (int)$raw[3];
						$data[$a]['filename'] = 'xml_files/'.$file;
						$a++;
					}
				}
			}
		}
  }

  closedir($handler);

	foreach ($data as $block){

		$results = parse_ebay_xml($block['filename']);

		if (!empty($results)){
			
			foreach ($results['item'] as $item){

			// sort category ID
			// $category[0][0] = $item['category'];
			// $category[1][0] = $item['secondary_category'];
			// krsort($category);

			if ($item['category'] == ''){
				$item['category'] = $item['secondary_category'];
				$item['category_lvl_2'] = $item['secondary_category_lvl_2'];
				$item['category_lvl_3'] = $item['secondary_category_lvl_3'];
				$item['category_lvl_4'] = $item['secondary_category_lvl_4'];
				$item['secondary_category'] = '';
				$item['secondary_category_lvl_2'] = '';
				$item['secondary_category_lvl_3'] = '';
				$item['secondary_category_lvl_4'] = '';
			}

			// is this a duplicate listing?
			$sql = "SELECT l.id
								FROM listings AS l, listings_seller AS ls
							 WHERE l.id = ls.listing_id
							 	 AND l.ebay_title = '".mysql_real_escape_string(ucwords(strtolower($item['title'])))."'
							 	 AND ls.seller_id = '".mysql_real_escape_string($item['seller_name'])."'
							 	 AND l.dupe = '0'";
			$query = mysql_query($sql) or die(mysql_error());
			if ( (mysql_num_rows($query) > 0) || (trim($item['thumbnail']) == '') ){
				$dupe = '1';
			} else {
				$dupe = '0';
			}

			// add or update?
			$sql = "INSERT INTO listings(
							  id,
							  category_1,
							  category_1_lvl2,
							  category_1_lvl3,
							  category_1_lvl4,
							  category_2,
							  category_2_lvl2,
							  category_2_lvl3,
							  category_2_lvl4,
							  link_id,
							  ebay_title,
							  ebay_subtitle,
							  ebay_url,
							  listing_type,
							  bid_count,
							  start_time,
							  end_time,
							  ebay_site,
							  ebay_status,
							  dupe,
							 	active
							)VALUES(
							  '".$item['item_id']."',
							  '".$item['category']."',
							  '".$item['category_lvl_2']."',
							  '".$item['category_lvl_3']."',
							  '".$item['category_lvl_4']."',
							  '".$item['secondary_category']."',
							  '".$item['secondary_category_lvl_2']."',
							  '".$item['secondary_category_lvl_3']."',
							  '".$item['secondary_category_lvl_4']."',
							  '".mysql_real_escape_string($item['link_id'])."',
							  '".mysql_real_escape_string(ucwords(strtolower($item['title'])))."',
							  '".mysql_real_escape_string(ucwords(strtolower($item['subtitle'])))."',
							  '".mysql_real_escape_string($item['url'])."',
							  '".$item['listing_type']."',
							  '".$item['bid_count']."',
							  '".unix_time($item['start_time'])."',
							  '".unix_time($item['end_time'])."',
							  '".$item['site']."',
							  '".$item['listing_status']."',
							  '".$dupe."',
							 	'0'
							)ON DUPLICATE KEY UPDATE
								ebay_status = '".$item['listing_status']."'";

			if ($query = mysql_query($sql)){

// update listings_ebay_categories

				$sql = "INSERT INTO listings_ebay_categories(
									listing_id,
									ebay_category_id,
									ebay_category_name,
									ebay_second_category_id,
									ebay_second_category_name
								)VALUES(
									'".$item['item_id']."',
									'".$item['category_id']."',
									'".mysql_real_escape_string($item['category_name'])."',
									'".$item['secondary_category_id']."',
									'".mysql_real_escape_string($item['secondary_category_name'])."'
								)ON DUPLICATE KEY UPDATE
									ebay_second_category_id = '".$item['secondary_category_id']."'"; // another way to keep insert/update as one query?

				$query = mysql_query($sql) or die (mysql_error());

// update listings_images

				$sql = "INSERT INTO listings_images(
								  listing_id,
								  image_folder,
								  image_name,
								  thumbnail,
								  ebay_images_1,
								  ebay_images_2,
								  ebay_images_3,
								  ebay_images_4,
								  ebay_images_5,
								  ebay_images_6,
								  ebay_images_7,
								  ebay_images_8,
								  ebay_images_9,
								  ebay_images_10,
								  ebay_images_11,
								  ebay_images_12
								)VALUES(
								  '".$item['item_id']."',
								  '".strtolower($item['image_name'][0])."',
								  '".$item['image_name']."',
								  '".mysql_real_escape_string($item['thumbnail'])."',
								  '".mysql_real_escape_string($item['image_url_1'])."',
								  '".mysql_real_escape_string($item['image_url_2'])."',
								  '".mysql_real_escape_string($item['image_url_3'])."',
								  '".mysql_real_escape_string($item['image_url_4'])."',
								  '".mysql_real_escape_string($item['image_url_5'])."',
								  '".mysql_real_escape_string($item['image_url_6'])."',
								  '".mysql_real_escape_string($item['image_url_7'])."',
								  '".mysql_real_escape_string($item['image_url_8'])."',
								  '".mysql_real_escape_string($item['image_url_9'])."',
								  '".mysql_real_escape_string($item['image_url_10'])."',
								  '".mysql_real_escape_string($item['image_url_11'])."',
								  '".mysql_real_escape_string($item['image_url_12'])."'
								)ON DUPLICATE KEY UPDATE
									ebay_images_12 = '".$item['image_url_12']."'"; // another way to keep insert/update as one query?

				$query = mysql_query($sql) or die (mysql_error());

// update listings_payment

				$sql = "INSERT INTO listings_payment(
									listing_id,
									current_price,
									current_converted_price,
									lot_size,
									current_price_per,
									current_converted_price_per,
									visa_mastercard,
									discover,
									american_express,
									paypal,
									money_order,
									moneybookers,
									company_check,
									see_description,
									best_offer
								)VALUES(
									'".$item['item_id']."',
									'".$item['current_price']."',
									'".$item['converted_price']."',
									'".$item['lot_size']."',
									'".$item['current_price_per']."',
									'".$item['converted_price_per']."',
									'".$item['visa_mastercard']."',
									'".$item['discover']."',
									'".$item['american_express']."',
									'".$item['paypal']."',
									'".$item['money_order']."',
									'".$item['moneybookers']."',
									'".$item['check']."',
									'".$item['see_description']."',
									'".$item['best_offer']."'
								)ON DUPLICATE KEY UPDATE
									best_offer = '".$item['best_offer']."'"; // another way to keep insert/update as one query?

				$query = mysql_query($sql) or die (mysql_error());

// update listings_seller

				$sql = "INSERT INTO listings_seller(
									listing_id,
									seller_id,
									seller_rating,
									seller_rating_star,
									seller_rating_positive,
									seller_rating_private
								)VALUES(
									'".$item['item_id']."',
									'".mysql_real_escape_string($item['seller_name'])."',
									'".$item['feedback_score']."',
									'".$item['feedback_star']."',
									'".$item['feedback_percent']."',
									'".$item['feedback_private']."'
								)ON DUPLICATE KEY UPDATE
									seller_rating = '".$item['feedback_score']."',
									seller_rating_star = '".$item['feedback_star']."',
									seller_rating_positive = '".$item['feedback_percent']."',
									seller_rating_private = '".$item['feedback_private']."'";

				$query = mysql_query($sql) or die (mysql_error());

// update listings_payment

				$sql = "INSERT INTO listings_shipping(
									listing_id,
									postal_code,
									country,
									ship_to_worldwide,
									ship_to_australia,
									ship_to_nowhere,
									shipping_calculated,
									shipping_flat,
									shipping_freight,
									shipping_unknown,
									shipping_free,
									shipping_cost
								)VALUES(
									'".$item['item_id']."',
									'".$item['postal_code']."',
									'".$item['country']."',
									'".$item['ship_to_worldwide']."',
									'".$item['ship_to_australia']."',
									'".$item['ship_to_nowhere']."',
									'".$item['shipping_calculated']."',
									'".$item['shipping_flat']."',
									'".$item['shipping_freight']."',
									'".$item['shipping_unknown']."',
									'".$item['shipping_free']."',
									'".$item['shipping_cost']."'
								)ON DUPLICATE KEY UPDATE
									shipping_cost = '".$item['shipping_cost']."'"; // another way to keep insert/update as one query?

				$query = mysql_query($sql) or die (mysql_error());

			} else {
				die (mysql_error());
			}
		}

		unlink($block['filename']);
			
		}
	}
}

  //====================//
 // parse returned XML //
//====================//
function parse_ebay_xml($filename){

	$xml = simplexml_load_file($filename);

	if ((string)$xml->Ack == 'Failure'){
		$listings['errormessage'] = (string)$xml->Errors->LongMessage;
		$listings['errorcode'] = (string)$xml->Errors->ErrorCode;
		$listings['errorparams'] = (string)$xml->Errors->ErrorParameters->Value;
		return $listings;
	} else {
	
		// get array of category id numbers
		$sql = "SELECT id, level_2, level_3, level_4, ebay_id FROM categories";
		$query = mysql_query($sql);
		while ($results = mysql_fetch_assoc($query)){
			$category[$results['ebay_id']]['id'] = $results['id'];
			$category[$results['ebay_id']]['level_2'] = $results['level_2'];
			$category[$results['ebay_id']]['level_3'] = $results['level_3'];
			$category[$results['ebay_id']]['level_4'] = $results['level_4'];
		}
	
		$listings['timestamp'] = (string)$xml->Timestamp;
		$listings['current_page'] = (string)$xml->PageNumber;
		$listings['total_pages'] = (string)$xml->TotalPages;
		$listings['total_items'] = (string)$xml->TotalItems;
	
		if (isset($xml->SearchResult->ItemArray->Item)){
			foreach ($xml->SearchResult->ItemArray->Item as $item){
				$a = (string)$item->ItemID;
				$listings['item'][$a]['item_id'] = (string)$item->ItemID;
				$listings['item'][$a]['best_offer'] = (strtolower((string)$item->BestOfferEnabled) == 'true') ? '1' : '0';
				$listings['item'][$a]['end_time'] = (string)$item->EndTime;
				$listings['item'][$a]['start_time'] = (string)$item->StartTime;
				$listings['item'][$a]['url'] = (string)$item->ViewItemURLForNaturalSearch;
				$listings['item'][$a]['listing_type'] = (string)$item->ListingType;
				
				$listings['item'][$a]['visa_mastercard'] = '0';
				$listings['item'][$a]['discover'] = '0';
				$listings['item'][$a]['american_express'] = '0';
				$listings['item'][$a]['paypal'] = '0';
				$listings['item'][$a]['money_order'] = '0';
				$listings['item'][$a]['moneybookers'] = '0';
				$listings['item'][$a]['check'] = '0';
				$listings['item'][$a]['see_description'] = '0';		
				foreach ($item->PaymentMethods as $payment_method){
					 if((string)$payment_method == 'VisaMC') $listings['item'][$a]['visa_mastercard'] = '1';
					 if((string)$payment_method == 'Discover') $listings['item'][$a]['discover'] = '1';
					 if((string)$payment_method == 'AmEx') $listings['item'][$a]['american_express'] = '1';
					 if((string)$payment_method == 'PayPal') $listings['item'][$a]['paypal'] = '1';
					 if((string)$payment_method == 'MoCC') $listings['item'][$a]['money_order'] = '1';
					 if((string)$payment_method == 'Moneybookers') $listings['item'][$a]['moneybookers'] = '1';
					 if((string)$payment_method == 'PersonalCheck') $listings['item'][$a]['check'] = '1';
					 if((string)$payment_method == 'PaymentSeeDescription') $listings['item'][$a]['see_description'] = '1';
				}
				$listings['item'][$a]['title'] = trim((string)$item->Title);
				// remove all excect alphanumeric and spaces
				$clean_title = preg_replace("/[^a-zA-Z0-9\s]/", "", $listings['item'][$a]['title']);
				$clean_title = preg_replace('/\s+/', '-', trim($clean_title));
				$clean_title = strtolower($clean_title);
				if ($clean_title[0] == '-') $clean_title = substr($clean_title, 1);
				$listings['item'][$a]['link_id'] = $clean_title.'-'.substr($listings['item'][$a]['item_id'],-4);
				$listings['item'][$a]['image_name'] = $listings['item'][$a]['link_id'].'.jpg';
				$listings['item'][$a]['thumbnail'] = str_replace('8080_', '_', (string)$item->GalleryURL);
				$listings['item'][$a]['image_url_1'] = '';
				$listings['item'][$a]['image_url_2'] = '';
				$listings['item'][$a]['image_url_3'] = '';
				$listings['item'][$a]['image_url_4'] = '';
				$listings['item'][$a]['image_url_5'] = '';
				$listings['item'][$a]['image_url_6'] = '';
				$listings['item'][$a]['image_url_7'] = '';
				$listings['item'][$a]['image_url_8'] = '';
				$listings['item'][$a]['image_url_9'] = '';
				$listings['item'][$a]['image_url_10'] = '';
				$listings['item'][$a]['image_url_11'] = '';
				$listings['item'][$a]['image_url_12'] = '';
				$b = 1;
				foreach ($item->PictureURL as $picture_url){
					$listings['item'][$a]['image_url_'.$b] = (string)$picture_url;
					$b++;
				}

				// Determine location for australia
				$locale = '';
				$postal_code = (string)$item->PostalCode;
				switch ($postal_code){
					case ( ( $postal_code >= 2000 ) && ( $postal_code <= 2001 ) ):
						$locale = 'Sydney';
						break;
					case ( ( $postal_code >= 2600 ) && ( $postal_code <= 2601 ) ):
						$locale = 'Canberra';
						break;
					case ( ( $postal_code >= 3000 ) && ( $postal_code <= 3001 ) ):
						$locale = 'Melbourne';
						break;
					case ( ( $postal_code >= 4000 ) && ( $postal_code <= 4001 ) ):
						$locale = 'Brisbane';
						break;
					case ( ( $postal_code >= 5000 ) && ( $postal_code <= 5001 ) ):
						$locale = 'Adelaide';
						break;
					case ( ( $postal_code == 6000 ) ):
						$locale = 'Perth';
						break;
					case ( ( $postal_code >= 6837 ) && ( $postal_code <= 6848 ) ):
						$locale = 'Perth';
						break;
					case ( ( $postal_code >= 7000 ) && ( $postal_code <= 7001 ) ):
						$locale = 'Hobart';
						break;
					case ( ( $postal_code >= 0800 ) && ( $postal_code <= 0801 ) ):
						$locale = 'Darwin';
						break;
					case ( $postal_code == 2899 ):
						$locale = 'Norfolk Island';
						break;
					case ( $postal_code == 6798 ):
						$locale = 'Christmas Island';
						break;
					case ( $postal_code == 6799 ):
						$locale = 'Cocos (Keeling) Islands';
						break;
					case ( (($postal_code >= 1000) && ($postal_code <= 2599)) ):
						$locale = 'New South Wales';
						break;
					case ( (($postal_code >= 2619) && ($postal_code <= 2898)) ):
						$locale = 'New South Wales';
						break;
					case ( (($postal_code >= 2921) && ($postal_code <= 2999)) ):
						$locale = 'New South Wales';
						break;
					case ( (($postal_code >= 0200) && ($postal_code <= 0299)) ):
						$locale = 'Australian Capital Territory';
						break;
					case ( (($postal_code >= 2600) && ($postal_code <= 2618)) ):
						$locale = 'Australian Capital Territory';
						break;
					case ( (($postal_code >= 2900) && ($postal_code <= 2920)) ):
						$locale = 'Australian Capital Territory';
						break;
					case ( (($postal_code >= 3000) && ($postal_code <= 3999)) ):
						$locale = 'Victoria';
						break;
					case ( (($postal_code >= 8000) && ($postal_code <= 8999)) ):
						$locale = 'Victoria';
						break;
					case ( (($postal_code >= 4000) && ($postal_code <= 4999)) ):
						$locale = 'Queensland';
						break;
					case ( (($postal_code >= 9000) && ($postal_code <= 9999)) ):
						$locale = 'Queensland';
						break;
					case ( (($postal_code >= 5000) && ($postal_code <= 5999)) ):
						$locale = 'South Australia';
						break;
					case ( (($postal_code >= 6000) && ($postal_code <= 6797)) ):
						$locale = 'Western Australia';
						break;
					case ( (($postal_code >= 6800) && ($postal_code <= 6999)) ):
						$locale = 'Western Australia';
						break;
					case ( (($postal_code >= 7000) && ($postal_code <= 7999)) ):
						$locale = 'Tasmania';
						break;
					case ( (($postal_code >= 0800) && ($postal_code <= 0999)) ):
						$locale = 'Northern Territory';
						break;
					default:
						$locale = '';
						break;
				}

				$listings['item'][$a]['postal_code'] = $locale;
				
		    $listings['item'][$a]['category_id'] = (string)$item->PrimaryCategoryID;
		    $listings['item'][$a]['category_name'] = (string)$item->PrimaryCategoryName;
		    $listings['item'][$a]['category'] = (isset($category[$listings['item'][$a]['category_id']]['id']))?$category[$listings['item'][$a]['category_id']]['id']:'';
		    $listings['item'][$a]['category_lvl_2'] = ((isset($category[$listings['item'][$a]['category_id']]['level_2'])) && ($category[$listings['item'][$a]['category_id']]['level_2'] != 'x'))?$category[$listings['item'][$a]['category_id']]['level_2']:'';
		    $listings['item'][$a]['category_lvl_3'] = ((isset($category[$listings['item'][$a]['category_id']]['level_3'])) && ($category[$listings['item'][$a]['category_id']]['level_3'] != 'x'))?$category[$listings['item'][$a]['category_id']]['level_3']:'';
		    $listings['item'][$a]['category_lvl_4'] = ((isset($category[$listings['item'][$a]['category_id']]['level_4'])) && ($category[$listings['item'][$a]['category_id']]['level_4'] != 'x'))?$category[$listings['item'][$a]['category_id']]['level_4']:'';
		    $listings['item'][$a]['secondary_category_id'] = (string)$item->SecondaryCategoryID;
		    $listings['item'][$a]['secondary_category_name'] = (string)$item->SecondaryCategoryName;
				$listings['item'][$a]['secondary_category'] = (isset($category[$listings['item'][$a]['secondary_category_id']]['id']))?$category[$listings['item'][$a]['secondary_category_id']]['id']:'';
				if ($listings['item'][$a]['secondary_category'] != ''){
					$listings['item'][$a]['secondary_category_lvl_2'] = ((isset($category[$listings['item'][$a]['secondary_category_id']]['level_2'])) && ($category[$listings['item'][$a]['secondary_category_id']]['level_2'] != 'x'))?$category[$listings['item'][$a]['secondary_category_id']]['level_2']:'';
					$listings['item'][$a]['secondary_category_lvl_3'] = ((isset($category[$listings['item'][$a]['secondary_category_id']]['level_3'])) && ($category[$listings['item'][$a]['secondary_category_id']]['level_3'] != 'x'))?$category[$listings['item'][$a]['secondary_category_id']]['level_3']:'';
					$listings['item'][$a]['secondary_category_lvl_4'] = ((isset($category[$listings['item'][$a]['secondary_category_id']]['level_4'])) && ($category[$listings['item'][$a]['secondary_category_id']]['level_4'] != 'x'))?$category[$listings['item'][$a]['secondary_category_id']]['level_4']:'';
				}

				foreach ($item->Seller as $seller){
					$listings['item'][$a]['seller_name'] = (string)$seller->UserID;
					$listings['item'][$a]['feedback_score'] = (string)$seller->FeedbackScore;
					$listings['item'][$a]['feedback_percent'] = (string)$seller->PositiveFeedbackPercent;
					$listings['item'][$a]['feedback_private'] = (strtolower((string)$seller->FeedbackPrivate) == 'true') ? '1' : '0';
					$listings['item'][$a]['feedback_star'] = strtolower((string)$seller->FeedbackRatingStar);
				}
		    $listings['item'][$a]['bid_count'] = (string)$item->BidCount;
		    if (strtolower((string)$item->ListingStatus) == 'active'){
		    	$status = '1';
		    } else {
		    	$status = '0';
		    }
		    $listings['item'][$a]['listing_status'] = $status;

		    $listings['item'][$a]['ship_to_worldwide'] = '0';
		    $listings['item'][$a]['ship_to_australia'] = '0';
		    $listings['item'][$a]['ship_to_nowhere'] = '0';
		    
		    foreach ($item->ShipToLocations as $shipto){
			    if(strtolower((string)$shipto) == 'worldwide') $listings['item'][$a]['ship_to_worldwide'] = '1';
			    if(strtolower((string)$shipto) == 'au') $listings['item'][$a]['ship_to_australia'] = '1';
			    if(strtolower((string)$shipto) == 'none') $listings['item'][$a]['ship_to_nowhere'] = '1';
		    }
		    $listings['item'][$a]['site'] = (string)$item->Site;
		    $listings['item'][$a]['time_left'] = (string)$item->TimeLeft;
		    $listings['item'][$a]['lot_size'] = get_lot_size($listings['item'][$a]['title']);
		    $listings['item'][$a]['converted_price'] = (string)$item->ConvertedCurrentPrice;
		    $listings['item'][$a]['converted_price_per'] = ($listings['item'][$a]['lot_size'] == 0)?0:number_format(round($listings['item'][$a]['converted_price'] / $listings['item'][$a]['lot_size'], 2), 2, '.', '');
		    $listings['item'][$a]['current_price'] = (string)$item->CurrentPrice;
		    $listings['item'][$a]['current_price_per'] = ($listings['item'][$a]['lot_size'] == 0)?0:number_format(round($listings['item'][$a]['current_price'] / $listings['item'][$a]['lot_size'], 2), 2, '.', '');
	
				$listings['item'][$a]['shipping_calculated'] = '0';
				$listings['item'][$a]['shipping_flat'] = '0';
				$listings['item'][$a]['shipping_freight'] = '0';
				$listings['item'][$a]['shipping_unknown'] = '0';
				$listings['item'][$a]['shipping_free'] = '0';
	
				foreach ($item->ShippingCostSummary as $shipping_summary){
					if((string)$shipping_summary->ShippingServiceCost == '0.0') $listings['item'][$a]['shipping_free'] = '1';
		      $listings['item'][$a]['shipping_cost'] = (string)$shipping_summary->ShippingServiceCost;
		      if(strtolower((string)$shipping_summary->ShippingType) == 'calculated') $listings['item'][$a]['shipping_calculated'] = '1';
		      if(strtolower((string)$shipping_summary->ShippingType) == 'calculateddomesticflatinternational') $listings['item'][$a]['shipping_calculated'] = '1';
		      if(strtolower((string)$shipping_summary->ShippingType) == 'flat') $listings['item'][$a]['shipping_flat'] = '1';
		      if(strtolower((string)$shipping_summary->ShippingType) == 'flatdomesticcalculatedinternational') $listings['item'][$a]['shipping_flat'] = '1';
		      if(strtolower((string)$shipping_summary->ShippingType) == 'freight') $listings['item'][$a]['shipping_freight'] = '1';
		      if(strtolower((string)$shipping_summary->ShippingType) == 'notspecified') $listings['item'][$a]['shipping_unknown'] = '1';
				}
				$listings['item'][$a]['country'] = (string)$item->Country;
				$listings['item'][$a]['subtitle'] = trim((string)$item->Subtitle);
				foreach ($item->ItemSpecifics as $specifics){
					foreach ($specifics->NameValueList as $name_list){
						if ($name_list->Name == 'Condition'){
							$listings['item'][$a]['condition'] = (string)$name_list->Value;
						}
					}
				}
			}
		}
		return($listings);
	}
}

  //=======================================================================//
 // try to determine the number of items in lot by parsing the item title //
//=======================================================================//
function get_lot_size($title){
	
	// remove all excect alphanumeric and spaces
	// $clean_title = preg_replace("/[^a-zA-Z0-9\/\s]/", "", $title);
	$clean_title = strtolower(str_replace('-', ' ', trim($title)));
	$clean_title = str_replace(',', '', $clean_title);
	
	// match lot of #
	preg_match("/lot[s]* of (\d+)/i",$clean_title, $lot_size);
	if (isset($lot_size[1])){
		$lot = $lot_size[1];
		return $lot;
	} else {
		$lot_size = array();
	}
	
	// match #pieces #pc or #pcs or #ct or #count or #pair(s)
	preg_match("/(\s|\A)(\d+)\s*(x|pcs|pc|pieces|ct|count|pair|pairs|prs|lot|pcs\/lot|\/lot|pcslot|\+)(\s|\Z)+/i",$clean_title, $lot_size);
	if (isset($lot_size[2])){
		$lot = $lot_size[2];
		return $lot;
	} else {
		$lot_size = array();
	}
	
	// match lot # or x # or lotof
	preg_match("/(\s|\A)+(lot|x|lotof|pallet of)\s*+(\d+)/i",$clean_title, $lot_size);
	if (isset($lot_size[3])){
		$lot = $lot_size[3];
		return $lot;
	} else {
		$lot_size = array();
	}
	
	// match (#) or *(#)*
	preg_match("/(\s|\A)+(\(|\*)(\d+)(\)|\*)/i",$clean_title, $lot_size);
	if (isset($lot_size[3])){
		$lot = $lot_size[3];
		return $lot;
	} else {
		$lot_size = array();
	}
	
	// as a last resort match # (number at beginning of title)
	preg_match("/\A(\d+)\s+/i",$title, $lot_size);
	if ((isset($lot_size[1])) && ($lot_size[1] < 60001)){
		$lot = $lot_size[1];
		if (($lot < 1900) || ($lot > date("Y"))){ // filter out years
			return $lot;
		}
	} else {
		$lot_size = array();
	}

	// else cannot match... return 0
	return '0';
	
}

  //=============================================//
 // Download Queued Images & Set Product Active //
//=============================================//
function download_images( $num_images=40 ){
	
	$images = array();
	
	// create an array of images to download
	$sql = "SELECT l.id, l.ebay_site, li.image_folder, li.image_name, li.thumbnail, li.ebay_images_1, li.ebay_images_2, li.ebay_images_3, li.downloaded
						FROM listings AS l, listings_images AS li
					 WHERE l.get_image = '0'
					 	 AND l.dupe = '0'
					   AND l.id = li.listing_id
					   AND li.thumbnail != ''
				ORDER BY end_time ASC
					 LIMIT {$num_images}";
	$query = mysql_query($sql) or die (mysql_error());
	$a = 0;
	while ($results = mysql_fetch_assoc($query)){
		$images[$a]['ebay_id'] = $results['id'];
		$images[$a]['image_source'] = $results['thumbnail'];
		$images[$a]['image_destination'] = $results['image_folder'].'/'.$results['image_name'];
		if ($results['ebay_site'] == 'Liquid'){
			$images[$a]['resize'] = 1;
			$images[$a]['site'] = 'Liquid';
		} else {
			$images[$a]['resize'] = 0;
			$images[$a]['site'] = 'Ebay';
		}
		if ($results['downloaded'] == 0){
			if ($results['ebay_images_1'] != ''){
				$a++;
				$images[$a]['ebay_id'] = $results['id'];
				$images[$a]['image_source'] = $results['ebay_images_1'];
				$images[$a]['image_destination'] = $results['image_folder'].'/'.str_replace('.jpg', '-1.jpg', $results['image_name']);
				$images[$a]['resize'] = 1;
			}
			if ($results['ebay_images_2'] != ''){
				$a++;
				$images[$a]['ebay_id'] = $results['id'];
				$images[$a]['image_source'] = $results['ebay_images_2'];
				$images[$a]['image_destination'] = $results['image_folder'].'/'.str_replace('.jpg', '-2.jpg', $results['image_name']);
				$images[$a]['resize'] = 1;
			}
			if ($results['ebay_images_3'] != ''){
				$a++;
				$images[$a]['ebay_id'] = $results['id'];
				$images[$a]['image_source'] = $results['ebay_images_3'];
				$images[$a]['image_destination'] = $results['image_folder'].'/'.str_replace('.jpg', '-3.jpg', $results['image_name']);
				$images[$a]['resize'] = 1;
			}
		}
		$a++;
	}
  
	$save_to='../product-images/';
	 
	$mh = curl_multi_init();
	foreach ($images as $i => $image) {
	    $g=$save_to.$image['image_destination'];
	    if(!is_file($g)){
	        $conn[$i]=curl_init($image['image_source']);
	        $fp[$i]=fopen ($g, "w");
	        curl_setopt ($conn[$i], CURLOPT_FILE, $fp[$i]);
	        curl_setopt ($conn[$i], CURLOPT_HEADER ,0);
	        curl_setopt ($conn[$i], CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; .NET CLR 3.0.04506; .NET CLR 1.1.4322; InfoPath.2; .NET CLR 3.5.21022)");
	        curl_setopt ($conn[$i], CURLOPT_CONNECTTIMEOUT,60);
	        curl_multi_add_handle ($mh,$conn[$i]);
	    }
	}
	do {
	    $n=curl_multi_exec($mh,$active);
	}
	while ($active);
	foreach ($images as $i => $image) {
		if($conn[$i] != ''){
	  	curl_multi_remove_handle($mh,$conn[$i]);
	  	curl_close($conn[$i]);
	  	fclose ($fp[$i]);
	  }
		$g=$save_to.$image['image_destination'];
		if ($image['resize'] == 1){
			if ($image['site'] == 'Liquid'){
				imageResize($g, 'Liquid');
				echo 'Liquid';
			} else {
				imageResize($g);
			}
		}
		if( (is_file($g)) && (filesize($g) > 100) ){
			if ($image['resize'] == 0){
				$sql = "UPDATE listings SET get_image = '1' WHERE id = '".$image['ebay_id']."'";
				$query = mysql_query($sql);
			} else {
				$sql = "UPDATE listings_images SET downloaded = '1' WHERE listing_id = '".$image['ebay_id']."'";
				$query = mysql_query($sql);
			}
		} else {
			unlink($g);
		}
	}
	curl_multi_close($mh);
	return;
}

  //=================================================//
 // Remove Expired Listings and Clear Data on Dupes //
//=================================================//
function sweep_listings(){
	
	$current_time = gmdate(time());
	
	// get an array of ids and images to delete
	$sql = "SELECT l.id, li.image_folder, li.image_name
						FROM listings AS l, listings_images AS li
					 WHERE l.id = li.listing_id
					 	 AND l.end_time < {$current_time}";
	$query = mysql_query($sql) or die (mysql_error());
	while ($todelete = mysql_fetch_assoc($query)){
		$listing_id = $todelete['id'];
		$listing_image = '../product-images/'.$todelete['image_folder'].'/'.$todelete['image_name'];

		// remove product from database
		$subsql = "DELETE l.*, lec.*, li.*, lp.*, ls.*, lsh.*
								 FROM listings AS l,
									 		listings_ebay_categories AS lec,
									 		listings_images AS li,
									 		listings_payment AS lp,
									 		listings_seller AS ls,
									 		listings_shipping AS lsh
						 		WHERE l.id = lec.listing_id
							 		AND l.id = li.listing_id
							 		AND l.id = lp.listing_id
							 		AND l.id = ls.listing_id
							 		AND l.id = lsh.listing_id
							 		AND l.id = {$listing_id}";
	
		$subquery = mysql_query($subsql) or die (mysql_error());

		// delete image file
		@unlink($listing_image);
		@unlink(str_ireplace('.jpg','-1.jpg', $listing_image));
		@unlink(str_ireplace('.jpg','-2.jpg', $listing_image));
		@unlink(str_ireplace('.jpg','-3.jpg', $listing_image));

	}

	// $deleted = mysql_affected_rows();

	return;
}

  //==============================================//
 // Resize images to 280x280 for product details //
//==============================================//
	function imageResize($filename, $site='ebay') {
		
		$dimensions = getimagesize($filename);
		$width = $dimensions[0];
		$height = $dimensions[1];

		if ($site == 'Liquid'){
			$imgs[0]['destination'] = str_replace('.jpg', '-1.jpg', $filename);
			$imgs[0]['target_size'] = 280;
			$imgs[1]['destination'] = $filename;
			$imgs[1]['target_size'] = 96;
		} else {
			$imgs[0]['destination'] = $filename;
			$imgs[0]['target_size'] = 280;
		}

		$image_type = 'jpg';

		if( $dimensions[2] == IMAGETYPE_GIF ) $image_type = 'gif';
		if( $dimensions[2] == IMAGETYPE_PNG ) $image_type = 'png';

		foreach ( $imgs as $img ){

			if ( ($width < $img['target_size']) && ($height < $img['target_size']) ){
				if ($img['destination'] != $filename){
					copy($filename, $img['destination']); // if image is already correct size just copy
				}
			} else {

				if ($width > $height) {
					$percentage = ($img['target_size'] / $width);
				} else {
					$percentage = ($img['target_size'] / $height);
				}
				
				//gets the new value and applies the percentage, then rounds the value
				$newwidth = round($width * $percentage);
				$newheight = round($height * $percentage);
				
				// Load
				$thumb = imagecreatetruecolor($newwidth, $newheight);

				if ($image_type == 'gif'){
					$source = imagecreatefromgif($filename);
				} elseif ($image_type == 'png'){
					$source = imagecreatefrompng($filename);
				} else {
					$source = imagecreatefromjpeg($filename);
				}

				// resize
				imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
				
				// save the image
				imagejpeg($thumb, $img['destination'], 85);
				
				// Free up memory
				imagedestroy($thumb);
			}

		}
		return;
	}

?>