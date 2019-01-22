<?php

require ('includes/application_top.php');

$title = SITE_NAME.' - Manual Feed Import';
$page_title = SITE_NAME.' - Manual Feed Import';
// SET VARIABLES
$total_new_products = 0;
$total_updated_products = 0;
$total_eol_products = 0;
$total_images_downloaded = 0;
$missing_images = array();

require ('includes/header.php');

?>
			<div id="search_bar" class="notify">
				<p></p>
			</div>
			<div class="main">
				<h2>Manually Import Product Feed</h2>
				<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
					<label for="file">Filename:</label>
					<input type="file" name="file" id="file" />
					<input type="submit" name="submit" value="Submit" />
				</form>
			</div>

<?php
// IF FILE EXISTS CONTINUE
if ((isset($_POST['submit'])) && ($_FILES['file']['tmp_name'] != '')){

// DEFINE COLUMN NAMES. LEAVE IN LETSTALK'S FORMAT IN CASE OF FUTURE CHANGES...

	$col[0] =  'name';
	$col[1] =  'keywords';
	$col[2] =  'description';
	$col[3] =  'sku';
	$col[4] =  'buy_url';
	$col[5] =  'available';
	$col[6] =  'imageurl';
	$col[7] =  'retailprice';
	$col[8] =  'new_before_rebate';
	$col[9] =  'new_after_rebate';
	$col[10] = 'phoneprice';
	$col[11] = 'currency';
	$col[12] = 'upc';
	$col[13] = 'promotionaltext';
	$col[14] = 'advertisercategory';
	$col[15] = 'manufacturer';
	$col[16] = 'manufacturerid';
	$col[17] = 'special';
	$col[18] = 'thirdpartycategory';
	$col[19] = 'instock';
	$col[20] = 'condition';
	$col[21] = 'standardshippingcost';
	$col[22] = 'title';
	$col[23] = 'plans';

// PARSE THE TAB DELIMITED FILE
	$row = 1;
	$a = 0;
	$handle = fopen($_FILES['file']['tmp_name'], "r");
	while (($data = fgetcsv($handle, 15000, ",")) !== FALSE) {
	    $num = count($data);
	    if ($row > 2){
	    	for ($c=0; $c < $num; $c++) {
	    		$id = $col[$c];
	    		$value = $data[$c];

// ID
					if ($id == 'sku'){
						$prodfeed[$a]['sku'] = trim($value);
					}

// TITLE (trim at 70 characters)
					if ($id == 'name'){
						
						// clean name
						$clean_name = explode('(', $value);
						$cleaned_name = trim(str_replace(array('  ', ' - '),' ',$clean_name[0]));
						if (strlen($cleaned_name) > 70){
							$chopped_name = substr($cleaned_name, 0, 69);
							$cleaned_name = substr($chopped_name, 0, strripos($chopped_name, ' '));
						}
						$prodfeed[$a]['title'] = trim($cleaned_name);
						$prodfeed[$a]['link_title'] = preg_replace("/[^\sa-zA-Z0-9]/", "", $prodfeed[$a]['title']);
						$prodfeed[$a]['link_url'] = str_replace(' ', '-', strtolower(trim($prodfeed[$a]['link_title'])));
						$prodfeed[$a]['link_url'] = str_replace('--', '-', strtolower(trim($prodfeed[$a]['link_url'])));
						$prodfeed[$a]['meta_title'] = $prodfeed[$a]['link_title'];
					}

// LINK - IMAGE_LINK - PRODUCT_TYPE
					if ($id == 'imageurl'){
						
						$prodfeed[$a]['image_url'] = str_replace('www.letstalk.com', 'www.onlinemobilestore.com', $value);
						$prodfeed[$a]['thumbnail_url'] = str_replace('_pdi.gif', '_thumb.gif', $prodfeed[$a]['image_url']);
						$prodfeed[$a]['browse_image_url'] = str_replace('_pdi.gif', '_brw.gif', $prodfeed[$a]['image_url']);
						$prodfeed[$a]['two_image_url'] = str_replace('_pdi.gif', '_pdi_tfd.gif', $prodfeed[$a]['image_url']);

						// extract product number
						$raw_product_id = substr($value, (strripos($value, '/')+1));
						$parts_product_id = explode('_', $raw_product_id);
						$product_id = $parts_product_id[0];
						
						// is phone or accessory?
						if (strpos($value, 'cell-phones') === FALSE){
							//accessory
							$prodfeed[$a]['type'] = 'accessory';
							$prodfeed[$a]['link'] = 'http://www.onlinemobilestore.com/accessories/productdetail.htm?prId='.$product_id;
							//type of accessory - default accessories
							$accessory_id = substr($value, (strripos($value, '/')-3), 3);
							switch ($accessory_id){
								case 105:
									$prodfeed[$a]['product_type'] = '"Electronics > Electronics Accessories > Power > Batteries > Mobile Phone Batteries"';
									break;
								case 107:
									$prodfeed[$a]['product_type'] = '"Luggage > Mobile Phone Cases"';
									break;
								case 108:
									$prodfeed[$a]['product_type'] = '"Electronics > Communications > Headsets"';
									break;
								case 110:
									$prodfeed[$a]['product_type'] = '"Electronics > Electronics Accessories > Power > Chargers > Mobile Phone Chargers"';
									break;
								default:
									$prodfeed[$a]['product_type'] = '"Electronics > Communications > Telephony > Mobile Phone Accessories"';
									break;
								}
							$prodfeed[$a]['accessory_buy_url'] = 'http://www.onlinemobilestore.com/upgrades/buytype.htm?prId='.$product_id;
						} else {
							//phone
							$prodfeed[$a]['lts_id'] = $product_id;
							$prodfeed[$a]['type'] = 'phone';
							$prodfeed[$a]['link'] = 'http://www.onlinemobilestore.com/cell-phones/productdetail.htm?prId='.$product_id;
							$prodfeed[$a]['product_type'] = '"Electronics > Communications > Telephony > Mobile Phones"';
							$prodfeed[$a]['accessory_buy_url'] = '';
						};
						
					}

// BRAND
					if ($id == 'manufacturer'){
						if ( ($value != '') && ($value != 'Aftermarket') ){
							if (strtolower($value) == 'blackberry') $value = 'RIM';
							if (strtolower($value) == 'ericsson') $value = 'Sony Ericsson';
							$prodfeed[$a]['brand'] = trim($value);
						} else {
							$prodfeed[$a]['brand'] = '';
						}
					}

// CONDITION
					if ($id == 'condition'){
						$prodfeed[$a]['condition'] = trim(strtolower($value));
					}

// NEW CONTRACT BEFORE REBATE PRICE
					if ($id == 'new_before_rebate'){
						$prodfeed[$a]['price_today'] = trim(strtolower($value));
					}

// NEW CONTRACT AFTER REBATE PRICE
					if ($id == 'new_after_rebate'){
						$prodfeed[$a]['price_after_rebates'] = trim(strtolower($value));
					}

// RETAIL PRICE
					if ($id == 'retailprice'){
						$prodfeed[$a]['retail_price'] = trim(strtolower($value));
					}

// PHONE ONLY PRICE
					if ($id == 'phoneprice'){
						$prodfeed[$a]['phone_price'] = trim(strtolower($value));
					}

// SHIPPING COST
					if ($id == 'standardshippingcost'){
						$prodfeed[$a]['shipping_cost'] = trim(strtolower($value));
					}

// CARRIER
					if ($id == 'thirdpartycategory'){
						$prodfeed[$a]['carrier'] = trim(strtolower($value));
						$prodfeed[$a]['carrier_id'] = '';
						
						// carrier ID
						if ($prodfeed[$a]['carrier'] == 'alaska digitel') $prodfeed[$a]['carrier_id'] = '702';
						if ($prodfeed[$a]['carrier'] == 'alltel wireless') $prodfeed[$a]['carrier_id'] = '567';
						if ($prodfeed[$a]['carrier'] == 'at&t') $prodfeed[$a]['carrier_id'] = '596';
						if ($prodfeed[$a]['carrier'] == 'centennial wireless') $prodfeed[$a]['carrier_id'] = '677';
						if ($prodfeed[$a]['carrier'] == 'cricket') $prodfeed[$a]['carrier_id'] = '623';
						if ($prodfeed[$a]['carrier'] == 'go phone from at&t') $prodfeed[$a]['carrier_id'] = '696';
						if ($prodfeed[$a]['carrier'] == 'net10') $prodfeed[$a]['carrier_id'] = '687';
						if ($prodfeed[$a]['carrier'] == 'ntelos') $prodfeed[$a]['carrier_id'] = '600';
						if ($prodfeed[$a]['carrier'] == 'sprint') $prodfeed[$a]['carrier_id'] = '545';
						if ($prodfeed[$a]['carrier'] == 't-mobile') $prodfeed[$a]['carrier_id'] = '543';
						if ($prodfeed[$a]['carrier'] == 'tracfone') $prodfeed[$a]['carrier_id'] = '614';
						if ($prodfeed[$a]['carrier'] == 'u.s. cellular') $prodfeed[$a]['carrier_id'] = '566';
						if ($prodfeed[$a]['carrier'] == 'verizon wireless') $prodfeed[$a]['carrier_id'] = '660';
						if ($prodfeed[$a]['carrier'] == 'your current gsm carrier') $prodfeed[$a]['carrier_id'] = '999';
					}

// CARRIER
					if ($id == 'thirdpartycategory'){
						if ($value == 'NTELOS') $value = 'nTelos';
						$prodfeed[$a]['carrier'] = trim($value);
					}

// UPC
					if ($id == 'upc'){
						$prodfeed[$a]['upc'] = trim($value);
					}

// PAYMENT ACCEPTED
					$prodfeed[$a]['payment_accepted'] = 'Visa,MasterCard,Discover,AmericanExpress';

// BUYURL (temp)
					if ($id == 'buy_url'){
						$temp = str_replace('http://','',$value);
						$tempurl = str_replace('letstalk.com', 'onlinemobilestore.com', $temp);
						$prodfeed[$a]['buy_url'] = $tempurl;
						
						$cobrand_id = explode('=', $tempurl);
						$prodfeed[$a]['cobrand_id'] = $cobrand_id[1];
					}

// BESTSELLER
					if ($id == 'promotionaltext'){
						if ($value != ''){
							$prodfeed[$a]['bestseller'] = '1';
						} else {
							$prodfeed[$a]['bestseller'] = '0';
						}
		    	}

// DESCRIPTION
					if ($id == 'description'){
						$description = str_ireplace('letstalk', 'OnlineMobileStore', $value);
						$raw_description = explode('includes:', $description);

						$clean_description = substr($raw_description[0], 0, (strripos($raw_description[0], '.')+1));
						$clean_description = str_replace('  ', ', ', $clean_description);

						$prodfeed[$a]['description'] = $clean_description;
					}

		    }
	    	$a++;
	    }
	    $row++;
	}
	fclose($handle);

// SET ALL TO NOT UPDATED
	$sql = "UPDATE products
						 SET updated = 0";
	$query = mysql_query($sql);

// FINISHED WITH CSV FILE
	foreach ( $prodfeed AS $product){

// PRODUCT TYPE: PHONE
		if ($product['type'] == 'phone'){

// BRAND NAMES
		if ( ($product['brand'] != '') && ($product['brand'] != 'Aftermarket') ){
			
			// synchronize brand names
			if ($product['brand'] == 'UT Starcom') $product['brand'] = 'UTStarcom';
			if ($product['brand'] == 'LG InfoComm') $product['brand'] = 'LG';
			if ($product['brand'] == 'LG Infocomm') $product['brand'] = 'LG';
			if ($product['brand'] == 'LG Dare') $product['brand'] = 'LG';
			
			$brand_sql = "SELECT id
										FROM products_brand
										WHERE name = '".$product['brand']."'";
			$brand_query = mysql_query($brand_sql);
			if ( mysql_num_rows($brand_query) == 0 ){
	// INSERT NEW BRAND

				// get final weight number
				$brandweight_sql = "SELECT weight
														FROM products_brand
														ORDER by weight DESC
														LIMIT 1";
				$brandweight_query = mysql_query($brandweight_sql);
				while ($result = mysql_fetch_assoc($brandweight_query)){
					$brandweight = $result['weight'] + 10;
				}

				$link_url = str_replace(' ','-',$product['brand']);
				$link_url = strtolower(str_replace('&','-and-',$link_url));
				$new_brand_sql = "INSERT into products_brand (
												name,
												link_url,
												link_title,
												weight
											) VALUES (
												'".mysql_real_escape_string($product['brand'])."',
												'".mysql_real_escape_string($link_url)."',
												'".mysql_real_escape_string(strtolower($product['brand']))."',
												'".$brandweight."'
											)";
				$new_brand_query = mysql_query($new_brand_sql);
				$brand_sql = "SELECT id
											FROM products_brand
											WHERE name = '".$product['brand']."'";
				$brand_query = mysql_query($brand_sql);
			}
			while ($row = mysql_fetch_assoc($brand_query)){
				$product['brand_id'] = $row['id'];
			}
		} else {
			$product['brand_id'] = 0; //product has no brand associated with it
		}

// CARRIER NAMES

				$carrier_sql = "SELECT id
											FROM products_carrier
											WHERE name = '".$product['carrier']."'";
				$carrier_query = mysql_query($carrier_sql);
				if ( mysql_num_rows($carrier_query) == 0 ){
  // NEW CARRIER
  
  				// get final weight number
  				$carrierweight_sql = "SELECT weight
  															FROM products_carrier
  															ORDER by weight DESC
  															LIMIT 1";
  				$carrierweight_query = mysql_query($carrierweight_sql);
  				while ($result = mysql_fetch_assoc($carrierweight_query)){
  					$carrierweight = $result['weight'] + 10;
  				}
					$link_url = str_replace(' ','-',$product['carrier']);
					$link_url = str_replace('.','',$link_url);
					$link_url = strtolower(str_replace('&','-and-',$link_url));

					// define carrier images, but not for unlocked cellphones
					$carrier_images = array();
					if ($product['carrier_id'] != ''){
						$carrier_images['small']['title'] = 'small '.strtolower($product['carrier']).' logo';
						$carrier_images['small']['image'] = 'sm-'.$link_url.'-logo.gif';
						$carrier_images['small']['url'] = 'http://www.letstalk.com/img/corpLogos/plSm'.$product['carrier_id'].'.gif';
						$carrier_images['medium']['title'] = 'medium '.strtolower($product['carrier']).' logo';
						$carrier_images['medium']['image'] = 'med-'.$link_url.'-logo.gif';
						$carrier_images['medium']['url'] = 'http://www.letstalk.com/img/corpLogos/plMed'.$product['carrier_id'].'.gif';
						$carrier_images['large']['title'] = 'large '.strtolower($product['carrier']).' logo';
						$carrier_images['large']['image'] = 'lg-'.$link_url.'-logo.gif';
						$carrier_images['large']['url'] = 'http://www.letstalk.com/img/corpLogos/plLg'.$product['carrier_id'].'.gif';
	
						foreach ($carrier_images as $carrier_image){
							// download carrier images
							$ch = curl_init ($carrier_image['url']);
							$fp = fopen ('../images/carrier/'.$carrier_image['image'], "w");
							curl_setopt ($ch, CURLOPT_FILE, $fp);
							curl_setopt ($ch, CURLOPT_HEADER, 0);
							curl_setopt ($ch, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") );
							curl_exec ($ch);
							curl_close ($ch);
							fclose ($fp);
						}

					// insert new carrier
					$new_carrier_sql = "INSERT into products_carrier (
													id,
													name,
													small_image,
													small_image_title,
													medium_image,
													medium_image_title,
													large_image,
													large_image_title,
													link_url,
													link_title,
													weight
												) VALUES (
													'".$product['carrier_id']."',
													'".mysql_real_escape_string($product['carrier'])."',
													'".$carrier_images['small']['image']."',
													'".mysql_real_escape_string($carrier_images['small']['title'])."',
													'".$carrier_images['medium']['image']."',
													'".mysql_real_escape_string($carrier_images['medium']['title'])."',
													'".$carrier_images['large']['image']."',
													'".mysql_real_escape_string($carrier_images['large']['title'])."',
													'".mysql_real_escape_string($link_url)."',
													'".mysql_real_escape_string(strtolower($product['carrier']))."',
													'".$carrierweight."'
												)";
					$new_carrier_query = mysql_query($new_carrier_sql);
				}
			}

		// New Product or Updated Product
				$is_new_sql = "SELECT id
												 FROM products
												 WHERE cobrand_id = '".$product['cobrand_id']."'";
				$is_new_query = mysql_query($is_new_sql);
				if ( mysql_num_rows($is_new_query) > 0 ){

// EXISTING PRODUCT (just update price, bestseller )
					$update_product_sql = "UPDATE products
																 SET standard_price = '".$product['retail_price']."',
																		 price_today = '".$product['price_today']."',
																		 price_after_rebates = '".$product['price_after_rebates']."',
																		 phone_price = '".$product['phone_price']."',
																		 shipping_cost = '".$product['shipping_cost']."',
																		 price_text = '".default_price_text($product['price_after_rebates'])."',
																		 bestseller = '".$product['bestseller']."',
																		 updated = '1',
																		 sku = '".$product['sku']."'
																	WHERE cobrand_id = '".$product['cobrand_id']."'";
					$update_product_query = mysql_query($update_product_sql) or die (mysql_error());

				} else {

// NEW PRODUCT
  				// get final weight number
  				$productweight_sql = "SELECT weight
  														FROM products
  														ORDER by weight DESC
  														LIMIT 1";
  				$productweight_query = mysql_query($productweight_sql);
  				while ($result = mysql_fetch_assoc($productweight_query)){
  					$productweight = $result['weight'] + 10;
  				}

// INSERT NEW PRODUCT
					$new_product_sql = "INSERT INTO products (
																ltc_id,
																cobrand_id,
																brand_id,
																carrier_id,
																product_name,
																sku,
																model_number,
																standard_price,
																price_today,
																price_after_rebates,
																phone_price,
																shipping_cost,
																is_too_low_to_show,
																thumbnail_image_url,
																browse_image_url,
																image_url,
																two_image_url,
																product_url,
																link_url,
																link_title,
																meta_title,
																date_added,
																weight,
																active,
																updated
															) VALUES (
																'".$product['lts_id']."',
																'".$product['cobrand_id']."',
																'".$product['brand_id']."',
																'".$product['carrier_id']."',
																'".mysql_real_escape_string($product['title'])."',
																'".$product['sku']."',
																'',
																'".$product['retail_price']."',
																'".$product['price_today']."',
																'".$product['price_after_rebates']."',
																'".$product['phone_price']."',
																'".$product['shipping_cost']."',
																'',
																'".$product['thumbnail_url']."',
																'".$product['browse_image_url']."',
																'".$product['image_url']."',
																'".$product['two_image_url']."',
																'".$product['buy_url']."',
																'".$product['link_url']."',
																'".$product['link_title']."',
																'".$product['meta_title']."',
																'".time()."',
																'".$productweight."',
																'1',
																'1'
															)";
					$new_product_query = mysql_query($new_product_sql) or die (mysql_error());
					$new_product_id = mysql_insert_id();

					$total_new_products = $total_new_products + mysql_affected_rows();

// INSERT NEW PRODUCT - DESCRIPTION
					$new_product_sql = "INSERT INTO products_description (
																product_id,
																description
															) VALUES (
																'".$new_product_id."',
																'".mysql_real_escape_string($product['description'])."'
															)";
					$new_product_query = mysql_query($new_product_sql) or die (mysql_error());

// INSERT NEW PRODUCT - FEATURES AND PLANS
					$url = 'http://www.onlinemobilestore.com/cell-phones/productdetail.htm?prId='.$product['lts_id'];

					// grab source for page
					$ch = curl_init ($url);
					curl_setopt ($ch, CURLOPT_HEADER, 0);
					curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt ($ch, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") );
					$ltc_source = curl_exec ($ch);
					curl_close ($ch);
					
					// remove whitespace
					$raw_data = str_replace(array("\n", "\r", "\t", "\o", "\xOB", "  "), '', $ltc_source);
					$data = str_replace('<tr >','<tr class="odd">', $raw_data);
					
					//grab quick features
					preg_match("/<ul class=\"phnFeats\">(.*)<\/ul>/Ui",$data, $featblock);
					$rawfeats = explode ('<li>', $featblock[1]);
					$a = 1;
					$shortfeats = "<ul>\r\n";
					$feats = "<ul>\r\n";
					foreach ($rawfeats as $feat){
						if (trim($feat) != ''){
							$feats .= '<li>'.trim(strip_tags($feat))."</li>\r\n";
							if ($a <= 7){
								$shortfeats .= '<li>'.trim(strip_tags($feat))."</li>\r\n";
							}
							$a++;
						}
					}
					$shortfeats .= '</ul>';
					$feats .= '</ul>';
					
					//grab included accessories
					preg_match("/<ul class=\"incAcc\">(.*)<\/ul>/Ui",$data, $incblock);
					$rawincs = explode ('<li>', $incblock[1]);
					$incs = "<ul>\r\n";
					foreach ($rawincs as $inc){
						if (trim($inc) != ''){
							$incs .= '<li>'.trim(strip_tags($inc))."</li>\r\n";
						}
					}
					$incs .= "</ul>";
// INSERT QUICK FEATURES, SHORT_FEATURES AND INCLUDED_ACCESSORIES
					$new_product_sql = "UPDATE products_description
															SET short_features = '".mysql_real_escape_string($shortfeats)."',
																	quick_features = '".mysql_real_escape_string($feats)."',
																	included = '".mysql_real_escape_string($incs)."'
															WHERE product_id = '".$new_product_id."'";
					$new_product_query = mysql_query($new_product_sql) or die (mysql_error());

					//grab features block
					preg_match("/Top Cell Phone Features(.*)<!-- BEGIN FOOTER -->/Ui",$data, $block);
					preg_match_all("/<tr class=\"odd\">(.*)<\/tr>/Ui", $block[1], $matches);
					
					//reset specs array
					$specs = array();
					
					foreach($matches[0] as $feature){
						$line = strip_tags(str_replace('</th><td>', '---', $feature));
						$parts = explode('---', $line);
					
						switch ($parts[0]){
							case 'Speakerphone': $key = 'speakerphone'; break;
							case 'Bluetooth®': $key = 'bluetooth'; break;
							case 'Text Messaging Capable': $key = 'text_messaging'; break;
							case 'Voice-Activated Dialing': $key = 'voice_dialing'; break;
							case 'MP3 Player': $key = 'mp3_player'; break;
							case 'Touch Screen': $key = 'touch_screen'; break;
							case 'QWERTY - Keyboard': $key = 'qwerty'; break;
							case 'Push to Talk Capable': $key = 'push_to_talk'; break;
							case 'Removable Memory': $key = 'removable_memory'; break;
							case 'Phone Style': $key = 'phone_style'; break;
							case 'Keyboard Type': $key = 'keyboard_type'; break;
							case 'Flip': $key = 'flip'; break;
							case 'Bar': $key = 'bar'; break;
							case 'Multiple Colors Available': $key = 'multiple_colors'; break;
							case 'Data Card Type': $key = 'data_card_type'; break;
							case 'Voice Frequencies': $key = 'voice_frequencies'; break;
							case 'Operating System': $key = 'operating_system'; break;
							case 'Headset Jack Type': $key = 'headset_jack_type'; break;
							case 'Data Frequencies': $key = 'data_frequencies'; break;
							case 'Dimensions (H x W x D)': $key = 'dimensions'; break;
							case 'Weight (w/standard battery)': $key = 'weight'; break;
							case 'External Volume Control': $key = 'external_volume_control'; break;
							case 'Hearing Aid Compliance': $key = 'hearing_aid_compliance'; break;
							case 'Language Options': $key = 'language_options'; break;
							case 'Vibrating Alert': $key = 'vibrating_alert'; break;
							case 'Camera Resolution': $key = 'camera_resolution'; break;
							case 'Camera Flash': $key = 'camera_flash'; break;
							case 'Video Playback': $key = 'video_playback'; break;
							case 'Ringtones Included': $key = 'ringtones_included'; break;
							case 'Phone Color': $key = 'phone_color'; break;
							case 'Snap-on Faceplates or Covers': $key = 'snapon_covers'; break;
							case 'Multi-Use / PDA Phone': $key = 'pda_phone'; break;
							case 'Powered by Windows Mobile': $key = 'windows_mobile'; break;
							case 'Alarm Clock': $key = 'alarm_clock'; break;
							case 'Calculator': $key = 'calculator'; break;
							case 'Calendar': $key = 'calendar'; break;
							case 'Phone Book Capacity': $key = 'phone_book_capacity'; break;
							case 'IR Port': $key = 'ir_port'; break;
							case 'BREW-Enabled': $key = 'brew_enabled'; break;
							case 'Java-Enabled': $key = 'java_enabled'; break;
							case 'Type of Battery': $key = 'type_of_battery'; break;
							case 'Talk Time': $key = 'talk_time'; break;
							case 'Standby Time': $key = 'standby_time'; break;
							case 'Technology': $key = 'technology'; break;
							default: $key = ''; break;
						}
						if ($key != ''){
							$value = $parts[1];
							if ($value == 'Yes') $value = '1';
							if ($value == 'No') $value = '0';
							$specs[$key] = $value;
						}
					}
					
					$features_sql = "INSERT into products_features (
										product_id,
										speakerphone,
										bluetooth,
										text_messaging,
										voice_dialing,
										mp3_player,
										touch_screen,
										qwerty,
										push_to_talk,
										removable_memory,
										phone_style,
										keyboard_type,
										flip,
										bar,
										multiple_colors,
										data_card_type,
										voice_frequencies,
										operating_system,
										headset_jack_type,
										data_frequencies,
										dimensions,
										weight,
										external_volume_control,
										hearing_aid_compliance,
										language_options,
										vibrating_alert,
										camera_resolution,
										camera_flash,
										video_playback,
										ringtones_included,
										phone_color,
										snapon_covers,
										pda_phone,
										windows_mobile,
										alarm_clock,
										calculator,
										calendar,
										phone_book_capacity,
										ir_port,
										brew_enabled,
										java_enabled,
										type_of_battery,
										talk_time,
										standby_time,
										technology
									) VALUES (
										'".$new_product_id."',
										'".$specs['speakerphone']."',
										'".$specs['bluetooth']."',
										'".$specs['text_messaging']."',
										'".$specs['voice_dialing']."',
										'".$specs['mp3_player']."',
										'".$specs['touch_screen']."',
										'".$specs['qwerty']."',
										'".$specs['push_to_talk']."',
										'".$specs['removable_memory']."',
										'".mysql_real_escape_string($specs['phone_style'])."',
										'".mysql_real_escape_string($specs['keyboard_type'])."',
										'".$specs['flip']."',
										'".$specs['bar']."',
										'".$specs['multiple_colors']."',
										'".mysql_real_escape_string($specs['data_card_type'])."',
										'".mysql_real_escape_string($specs['voice_frequencies'])."',
										'".mysql_real_escape_string($specs['operating_system'])."',
										'".mysql_real_escape_string($specs['headset_jack_type'])."',
										'".mysql_real_escape_string($specs['data_frequencies'])."',
										'".mysql_real_escape_string($specs['dimensions'])."',
										'".mysql_real_escape_string($specs['weight'])."',
										'".$specs['external_volume_control']."',
										'".mysql_real_escape_string($specs['hearing_aid_compliance'])."',
										'".mysql_real_escape_string($specs['language_options'])."',
										'".$specs['vibrating_alert']."',
										'".mysql_real_escape_string($specs['camera_resolution'])."',
										'".$specs['camera_flash']."',
										'".$specs['video_playback']."',
										'".mysql_real_escape_string($specs['ringtones_included'])."',
										'".mysql_real_escape_string($specs['phone_color'])."',
										'".$specs['snapon_covers']."',
										'".$specs['pda_phone']."',
										'".$specs['windows_mobile']."',
										'".$specs['alarm_clock']."',
										'".$specs['calculator']."',
										'".$specs['calendar']."',
										'".mysql_real_escape_string($specs['phone_book_capacity'])."',
										'".$specs['ir_port']."',
										'".$specs['brew_enabled']."',
										'".$specs['java_enabled']."',
										'".mysql_real_escape_string($specs['type_of_battery'])."',
										'".mysql_real_escape_string($specs['talk_time'])."',
										'".mysql_real_escape_string($specs['standby_time'])."',
										'".mysql_real_escape_string($specs['technology'])."'
									)";
					$features_query = mysql_query($features_sql) or die (mysql_error());

// GRAB EXTRA IMAGES
					$extra_images_sql = "SELECT pd.product_id, p.ltc_id, p.product_name, c.name AS carrier_name
															 FROM products_description AS pd, products AS p, products_carrier AS c
															 WHERE p.cobrand_id = '".$product['cobrand_id']."'
															 	 AND pd.product_id = p.id
															 	 AND c.id = p.carrier_id
															 	 AND pd.xtra_image_checked = 0";
					$extra_images_query = mysql_query($extra_images_sql) or die(mysql_error());
	
					while ($results = mysql_fetch_assoc($extra_images_query)){
	
						unset($total_extra_images);
						unset($extra_images);
						unset($xtra_data);
						
						$extra_images_url = 'http://www3.letstalk.com/product/seeMoreImages.htm?prId='.$results['ltc_id'];
						
						$ch = curl_init ($extra_images_url);
						curl_setopt ($ch, CURLOPT_HEADER, 0);
						curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt ($ch, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") );
						$ltc_source = curl_exec ($ch);
						curl_close ($ch);
						
						preg_match_all("/document.XL.src='(.*)'/Ui", $ltc_source, $extra_images);
						
						$total_extra_images = count($extra_images[1]);
						if ($total_extra_images > 0){
							for ($a=1; ( ($total_extra_images >= $a) && ($a <= 12) ); $a++){
								if (stripos($extra_images[1][$a-1], '/img/prod/100/') === FALSE){ // extra images are not available for EOL products
									
									$xtraimage_suffix = substr($extra_images[1][$a-1], (strripos($extra_images[1][$a-1], '.') +1) );
									$xtraimage_name_clean = preg_replace("/[^\sa-zA-Z0-9]/", "", $results['product_name']);
									$xtraimage_name = str_replace(' ', '-', strtolower(trim($xtraimage_name_clean.'-xl-'.$a.'.'.$xtraimage_suffix)));
									
									// grab images, should not need to worry about uniques in this folder but carrier_name is available just in case
									$ch = curl_init ('http://www.letstalk.com'.$extra_images[1][$a-1]);
									$fp = fopen ('../images/extra/'.$xtraimage_name, "w");
									curl_setopt ($ch, CURLOPT_FILE, $fp);
									curl_setopt ($ch, CURLOPT_HEADER, 0);
									curl_setopt ($ch, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") );
									curl_exec ($ch);
									curl_close ($ch);
									fclose ($fp);
	
									// insert info in database
									$xtra_data .= ', xtra_image_'.$a.'_url = \''.$extra_images[1][$a-1].'\'';
									$xtra_data .= ', xtra_image_'.$a.' = \''.$xtraimage_name.'\'';
									$xtra_data .= ', xtra_image_'.$a.'_title = \'large image '.$a.' of '.str_replace('-',' ',$xtraimage_name_clean).'\'';
									$xtra_data .= ', xtra_image_'.$a.'_alt = \'image '.$a.' of '.str_replace('-',' ',$xtraimage_name_clean).'\'';
								}
							}
						}
						
						$insert_xtraimages_sql = "UPDATE products_description
																		  SET xtra_image_checked = '1'
																		  {$xtra_data}
																		  WHERE product_id = '".$new_product_id."'";
						$insert_xtraimages_query = mysql_query($insert_xtraimages_sql);
						
					}



				}

	} else {

// PRODUCT TYPE: ACCESSORY

	}
	}

// SET END OF LIFE IF ANY
					$eol_sql = "UPDATE products
											SET end_of_life = '1'
											WHERE updated = '0'";
					$eol_query = mysql_query($eol_sql);
			
					$total_eol_products = $total_eol_products + mysql_affected_rows();

// CREATE AN ARRAY OF IMAGES TO DOWNLOAD
		$missing_images_sql = "SELECT p.id, p.product_name, p.thumbnail_image, p.thumbnail_image_url, p.browse_image, p.browse_image_url, p.image, p.image_url, p.two_image, p.two_image_url,
																	b.name AS brand_name, c.name AS carrier_name
													 FROM products AS p, products_brand AS b, products_carrier AS c
													 WHERE p.brand_id = b.id
													 	 AND p.carrier_id = c.id
													 	 AND (p.thumbnail_image = ''
													 		OR p.browse_image = ''
													 		OR p.image = ''
													 		OR p.two_image_url = '')";
		$missing_images_query = mysql_query($missing_images_sql);
		$a = 0;
		while ($results = mysql_fetch_assoc($missing_images_query)){
			$missing_images[$a]['product_id'] = $results['id'];
			$missing_images[$a]['product_name'] = $results['product_name'];
			$missing_images[$a]['brand_name'] = $results['brand_name'];
			$missing_images[$a]['carrier_name'] = $results['carrier_name'];
			if($results['thumbnail_image'] == '') $missing_images[$a]['url']['thumbnails'] = $results['thumbnail_image_url'];
			if($results['browse_image'] == '') $missing_images[$a]['url']['small'] = $results['browse_image_url'];
			if($results['image'] == '') $missing_images[$a]['url']['image'] = $results['image_url'];
			if($results['two_image'] == '') $missing_images[$a]['url']['two'] = $results['two_image_url'];
			$a++;
		}

// DOWNLOAD IMAGES
		foreach($missing_images as $mi_id){
			foreach($mi_id['url'] as $mi_key => $mi_url){
				// create image name and verify does not exist
				$image_type = substr($mi_url, (strripos($mi_url, '.') +1) );
				$image_name_clean = preg_replace("/[^\sa-zA-Z0-9]/", "", $mi_id['product_name']);
				$image_name = str_replace(' ', '-', strtolower(trim($image_name_clean))).'.'.$image_type;
				$image_path = ($mi_key == 'image')?'../images/large/'.$image_name:'../images/'.$mi_key.'/'.$image_name;
				if (file_exists($image_path) == TRUE){
					// file exists change name by adding carrier name in front
					$image_name_brand = preg_replace("/[^\sa-zA-Z0-9]/", "", $mi_id['carrier_name']);
					$image_name_brand = str_replace(' ', '-', strtolower(trim($image_name_brand)));
					$image_path = ($mi_key == 'image')?'../images/large/'.$image_name_brand.'-'.$image_name:'../images/'.$mi_key.'/'.$image_name_brand.'-'.$image_name;
					if (file_exists($image_path) == TRUE){
						// use LTC image name
						$image_name = substr($mi_url, (strripos($mi_url, '/') +1) );
						$image_path = ($mi_key == 'image')?'../images/large/'.$image_name:'../images/'.$mi_key.'/'.$image_name;
					}
				}
				
				// download image (may need to check for faulty URLs in future)
				$ch = curl_init ($mi_url);
				$fp = fopen ($image_path, "w");
				curl_setopt ($ch, CURLOPT_FILE, $fp);
				curl_setopt ($ch, CURLOPT_HEADER, 0);
				curl_setopt ($ch, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") );
				curl_exec ($ch);
				curl_close ($ch);
				fclose ($fp);
				
				$alt_image_name = $image_name_clean;
				
				// insert image name in to database
				switch ($mi_key) {
					case 'thumbnails':
				    $image_col = 'thumbnail_image';
				    $image_name_clean = 'thumbnail of '.$image_name_clean;
				    break;
					case 'small':
				    $image_col = 'browse_image';
				    $image_name_clean = 'small picture of '.$image_name_clean;
				    break;
					case 'image':
				    $image_col = 'image';
				    $image_name_clean = 'picture of '.$image_name_clean;
				    	// create resized image at 130x130 in medium image folder
							$thumbnail = resize($image_path, 130, 130, '../images/medium/'.$image_name);
				    break;
					case 'two':
				    $image_col = 'two_image';
				    $image_name_clean = 'picture of two '.$image_name_clean;
				    break;
					default:
						$image_col = '';
				}

				if ($image_col != ''){
					$insert_name_sql = "UPDATE products
															SET ".$image_col." = '".$image_name."',
																	".$image_col.'_title'." = '".strtolower($image_name_clean)."',
																	".$image_col.'_alt'." = '".strtolower($alt_image_name)."'
															WHERE id = '".$mi_id['product_id']."'";
					$insert_name_query = mysql_query($insert_name_sql);
					$total_images_downloaded++;
				}
			}
		}

}
require ('includes/footer.php');
?>