<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Untitled Document</title>
    </head>
    <body>
        <?php
        /* escaping get varaiable to prevent SQL injections */
        $settd = mysql_real_escape_string(filter_input(INPUT_GET, 'test'));


        require_once dirname(__FILE__) . 'app/Mage.php';
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $resource = Mage::getSingleton('core/resource');
        $db_read = $resource->getConnection('core_read');

        //retieving some specific categories to make changes
        $categories = $db_read->fetchCol("SELECT entity_id FROM " . $resource->getTableName("catalog_category_entity") . " WHERE entity_id>8311 ORDER BY entity_id DESC");
        foreach ($categories as $category_id) {
            try {
                Mage::getModel("catalog/category")->load($category_id)->delete();
            } catch (Exception $e) {
                echo $e->getMessage() . "\n";
            }
        }

        //move categories to another parent category
        $categoryIds = array(8229);
        $parentId = 7980;


        foreach ($categoryIds as $categoryId) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            $category->move($parentId, null);
            unset($category);
        }


        //soap connection
        try {
            ini_set('default_socket_timeout', 5); //So time out is 5 seconds
            $client = new SoapClient("'https://production.pamark.fi/index.php/api/soap/?wsdl'", array(
                'exceptions' => true,
            ));
            $client->startSession();
            $sessionId = $client->login('admin', 'pamark');
        } catch (Exception $e) {
            echo 'sorry... our service is down';
        }


        include('simple_html_dom.php');

        ini_set('memory_limit', '1524M');
        $collection = Mage::getResourceModel('catalog/product_collection');
        $cou = 0;
        //processing each record to make and save changes
        foreach ($collection as $productm) {

            $product_id = $productm->getId();
            $product = Mage::getModel("catalog/product")->load($product_id);

            if ($product) {

                $productsurlm = trim($product->getSupplierProductUrl());
                $productsurl = str_ireplace('http://www.pamark.fi', '80.86.90.251', trim($productsurlm)); {
                    $existance = is_producturl($productsurl);
                    if ($existance == true) {
                        $html = file_get_html($productsurl);
                        $productshortDescription = "";
                        $stockinfo = $html->find('input[name=soft-enforce-stock-counts]', 0)->getAttribute('value');
                        if ($html->find('div.marketing-msg', 0)) {
                            $productshortDescription = trim($html->find('div.marketing-msg', 0)->plaintext);
                        } else {
                            $productshortDescription = "";
                        }

                        if ($stockinfo > 0) {
                            $stockData = array(
                                'manage_stock' => 0,
                                'is_in_stock' => 1,
                                'qty' => $stockinfo,
                            );
                        } else {
                            $stockData = array(
                                'manage_stock' => 0,
                                'is_in_stock' => 0,
                                'qty' => $stockinfo,
                            );
                        }
                        $productdata = array(
                            'short_description' => trim($productshortDescription),
                        );



                        $result = $client->call($sessionId, 'catalog_product.update', array($product_id, $productdata));

                        $product->setShortDescription($productshortDescription);
                        $product->save();
                    }
                }
                $cou++;
            }
            if ($product->getOptions() != '') {
                foreach ($product->getOptions() as $opt) {
                    $opt->delete();
                }
                $product->setHasOptions(0)->save();
            }
        }




        /* this is the custom table just to keep track of counter varaibale incase of script stopped abnomally */
        $write5 = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write5->query("CREATE TABLE IF NOT EXISTS `test` (
				`testid` int(10) NOT NULL AUTO_INCREMENT,
				`counter` varchar(255) NOT NULL,
				PRIMARY KEY (`testid`)
			  ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2");

        $write5 = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write5->query("INSERT INTO test (`testid`, `counter`) VALUES ('1', '0');");


        $categoriesids = array();

        ini_set('memory_limit', '1524M');
        $sitemapurl = "https://production.pamark.fi/sitemapH.xml";

        $startmajork = 0;
        if (!$contents = simplexml_load_file($sitemapurl)) {
            exit('Failed to open ' . $sitemapurl);
        }
        $json = json_encode($contents);
        $matches = json_decode($json, TRUE);



        $product_collection = Mage::getModel('catalog/product')->getCollection()->setOrder('name', 'asc');

        $settd = 0;
        $countotdel = 0;
        foreach ($product_collection as $_product) {
            $settd++;
            $settd = mysql_real_escape_string($settd);
            $write5 = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write5->query("update test set counter=" . $settd . " where testid=1");
            $segproductid = trim($_product->getId());
            $segproductsku = trim($_product->getSku());
            $recordsexit = false;
            $f = fopen("newinventory.csv", "r");
            while (($row = fgetcsv($f, 80000, ',')) !== false) {
                $segtproductsku = trim($row[2]);
                if ($segtproductsku == $segproductsku) {
                    $recordsexit = true;
                    break;
                }
            }
            if ($recordsexit == false) {
                $countotdel++;
                $producttodel = Mage::getModel('catalog/product')->load($segproductid);
                $producttodel->delete();
            }

            fclose($f);
        }

        $proddistributer = "pamark";

        $drinkable = false;


        if (isset($_GET['start'])) {
            $sett = $_GET['start'];
            $settd = mysql_real_escape_string($settd);
            $write5 = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write5->query("update test set counter=" . $sett . " where testid=1");
        }


        $resource = Mage::getSingleton('core/resource');
        $conn = $resource->getConnection('core_read');
        $result = $conn->query("SELECT counter FROM test WHERE testid=1")->fetchAll();

        if (!$result) {
            die('Invalid query:' . mysql_error());
            exit;
        } else {
            $sett = $startmajork;
            $settd = mysql_real_escape_string($settd);
            $write5 = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write5->query("update test set counter=" . $sett . " where testid=1");
        }


        $startloop = 0;
        $totalrecs = count($matches['url']);
        $logolabels = "";
        $label_FSC = 0;
        $label_Joutsenmerkki = 0;
        $label_PEFC = 0;
        $label_kukka = 0;
        $label_Bra = 0;

        ob_start();
        ini_set('soap.wsdl_cache_enabled', 0);
        ini_set('soap.wsdl_cache_ttl', 0);
        ini_set('default_socket_timeout', 9999);
        if ($startmajork == "") {
            $startmajork = 0;
        }
        $mks = $startmajork;
        for ($mks = $startmajork; $mks < $totalrecs; $mks++) {
            $sett = $mks;
            $settd = mysql_real_escape_string($settd);
            $write5 = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write5->query("update test set counter=" . $sett . " where testid=1");
            $label_joutsenmerkki = 0;


            $recs = 0;
            if (($handle = fopen("newinventory.csv", "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 8000000, ",")) !== FALSE) {

                    if ($recs < $mks) {
                        $recs++;
                        continue;
                    }
                    $recs = mysql_real_escape_string($recs);
                    $write5 = Mage::getSingleton('core/resource')->getConnection('core_write');
                    $write5->query("update test set counter=" . $recs . " where testid=1");
                    $recs++;





                    $urlstring = trim($matches['url'][$mks]['loc']);
                    if ($existance == true) {

                        $productspecialPrice = 0;

                        ini_set('max_execution_time', '600');

                        ini_set('soap.wsdl_cache_enabled', 0);
                        ini_set('soap.wsdl_cache_ttl', 0);
                        ini_set('default_socket_timeout', 9999);


                        $produrl = $urlstring;


                        $html = file_get_html($produrl);


                        $productSku = $html->find('div.span7', 0)->children(1)->children(0)->plaintext;

                        $tags = get_meta_tags($produrl);

                        $doc = new DOMDocument();
                        $doc->loadHTML($html);
                        $nodes = $doc->getElementsByTagName('title');

                        $productMetaTitle = $nodes->item(0)->nodeValue;
                        $metas = $doc->getElementsByTagName('meta');
                        for ($i = 0; $i < $metas->length; $i++) {
                            $meta = $metas->item($i);
                            if ($meta->getAttribute('name') == 'description') {
                                $productMetaDescrition = $meta->getAttribute('content');
                            }
                            if ($meta->getAttribute('name') == 'keywords') {
                                $productMetaKeywords = $meta->getAttribute('content');
                            }
                        }

                        $categoryIds = array();

                        $cathtm = $html->find('ul.breadcrumb', 0);
                        $categoriesnames = array();
                        $cats = 1;
                        foreach ($cathtm->find('a') as $cathead) {
                            $catname = $cathead->plaintext;
                            if ($catname != "") {
                                array_push($categoriesnames, $catname);
                            }
                            $cats++;
                        }
                        print_r($categoriesnames);

                        array_pop($categoriesnames);

                        print_r($categoriesnames);

                        $productlogoimg = "";
                        $label_FSC = 0;
                        $label_Joutsenmerkki = 0;
                        $label_PEFC = 0;
                        $label_kukka = 0;
                        $label_Bra = 0;

                        $productlogoimghtml = $html->find('div.environment', 0);
                        $productlogoimghtmlcopy = $productlogoimghtml;

                        //seeting product image label as per defined standard
                        if ($productlogoimghtml->find('img', 0)) {
                            foreach ($productlogoimghtml->find('img') as $subimg) {
                                $logolabels = $logolabels . trim($subimg->getAttribute('alt')) . ";";
                                if (trim($subimg->getAttribute('alt')) == "FSC") {
                                    $label_FSC = 1;
                                    $productlogoimgFSC = "http://www.pamark.fi" . trim($subimg->getAttribute('src'));
                                }
                                if (trim($subimg->getAttribute('alt')) == "Joutsenmerkki") {
                                    $label_Joutsenmerkki = 1;
                                    $productlogoimgJoutsenmerkki = "http://www.pamark.fi" . trim($subimg->getAttribute('src'));
                                }
                                if (trim($subimg->getAttribute('alt')) == "PEFC") {
                                    $label_PEFC = 1;
                                    $productlogoimgPEFC = "http://www.pamark.fi" . trim($subimg->getAttribute('src'));
                                }
                                if (trim($subimg->getAttribute('alt')) == "EU-kukka") {
                                    $label_kukka = 1;
                                    $productlogoimgkukka = "http://www.pamark.fi" . trim($subimg->getAttribute('src'));
                                }
                                if (trim($subimg->getAttribute('alt')) == "Bra Miljöval") {
                                    $label_Bra = 1;
                                    $productlogoimgkukka = "http://www.pamark.fi" . trim($subimg->getAttribute('src'));
                                }
                                if ((trim($subimg->getAttribute('alt')) != "PEFC") && (trim($subimg->getAttribute('alt')) != "Joutsenmerkki") && (trim($subimg->getAttribute('alt')) != "FSC") && (trim($subimg->getAttribute('alt')) != "EU-kukka") && (trim($subimg->getAttribute('alt')) != "Bra Miljöval")) {
                                    $subimg->getAttribute('alt');
                                }
                            }
                        }
                        $productlogoimghtmlcopy = preg_replace('~>\s+<~', '><', trim($productlogoimghtmlcopy));

                        $stockinfo = $html->find('input[name=soft-enforce-stock-counts]', 0)->getAttribute('value');

                        $productDescription = $html->find('div.span7', 1)->outertext;


                        $productName = trim($html->find('h1.pamark', 0)->plaintext);
                        $custom_stock_estimate = trim($html->find('div.well', 0)->plaintext);

                        $productshortDescription = "";
                        $producttocheckdrink = "";
                        if ($html->find('div.marketing-msg', 0)) {
                            $productshortDescription = trim($html->find('div.marketing-msg', 0)->plaintext);

                            $producttocheckdrink = trim($html->find('div.marketing-msg', 0)->plaintext);
                            if ($producttocheckdrink == "Tuotteen hinta ei sisällä panttia. Pantit lisätään automaattisesti ostoskoriin.") {
                                $drinkable = true;
                            }
                        } else {
                            $productshortDescription = "";
                        }



                        //collection of images for current product
                        $productimages = array();
                        if ($html->find('a.slimbox', 0)) {
                            foreach ($html->find('a.slimbox') as $imghtml) {

                                $imagePath = $imghtml->getAttribute('href');
                                $imagePath = "http://www.pamark.fi" . $imagePath;
                                if (getimagesize($imagePath) !== false) {
                                    array_push($productimages, $imagePath);
                                }
                            }
                        }

                        if ($productlogoimg != "") {
                            if (getimagesize($productlogoimg) !== false) {
                                array_push($productimages, $productlogoimg);
                            }
                        }

                        print_r($productimages);
                        $productWeight = 1;
                        $custattc = 0;
                        $suboptions = array();
                        $suboptionsname = array();

                        if ($html->find('select.price-select', 0)) {

                            foreach ($html->find('select.price-select') as $cusomatt) {
                                $suboptions[$custattc] = array();
                                $suboptionsname[$custattc] = array();

                                $suboptionprice = array();
                                $suboptionslabels = array();
                                $suboptionsqty = array();

                                $suboptionsname[$custattc] = "Valitse koko";


                                $op = 0;
                                foreach ($cusomatt->find('option') as $option) {

                                    $suboptions[$custattc][$op] = trim($option->plaintext);
                                    if ($op == 0) {
                                        $productPricearr = explode("/", trim($option->plaintext));
                                    }
                                    $suboptionsqty[$op] = trim($option->getAttribute('data-qty'));
                                    $productPricearr2 = explode("/", trim($option->plaintext));
                                    $suboptionprice[$op] = trim(str_ireplace(",", '.', $productPrice2));
                                    $productPricearr22 = array_shift(explode('=', $productPricearr2[1]));
                                    $suboptionslabels[$op] = trim($productPricearr22);

                                    $op++;
                                }
                                $custattc++;
                            }
                        }
                        if (($key = array_search('Etusivu', $categoriesnames)) !== false) {
                            unset($categoriesnames[$key]);
                        }
                        $categoriesnames = array_values($categoriesnames);
                        $parmaincategory = 2;

                        for ($catmain = 0; $catmain < count($categoriesnames); $catmain++) {
                            $parentCategory = Mage::getModel('catalog/category')->load($parmaincategory);
                            $categoryname = ucwords(strtolower($categoriesnames[$catmain]));

                            $childCategory = Mage::getModel('catalog/category')->getCollection()
                                ->addAttributeToFilter('is_active', true)
                                ->addIdFilter($parentCategory->getChildren())
                                ->addAttributeToFilter('name', $categoryname)
                                ->getFirstItem();
                            if (null !== $childCategory->getId()) {
                                $maincategory = $childCategory->getId();
                            } else {

                                $subcatMode = 'PRODUCTS_AND_PAGE';
                                $maincategory = createCategory($parmaincategory, $categoryname, $subcatMode);
                            }
                            array_push($categoryIds, $maincategory);
                            $parmaincategory = $maincategory;
                        }
                        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $productSku);
                        //make required changed or update if product exist
                        if ($product) {
                            $productId = $product->getId();
                            $existcategoryIds = $product->getCategoryIds();
                            array_push($categoryIds, $existcategoryIds);

                            $ddqstring = "a:" . count($suboptionsqty) . ":{";
                            for ($lk = 0; $lk < count($suboptionsqty); $lk++) {
                                $ddqstring = $ddqstring . 's:' . strlen(number_format($suboptionsqty[$lk], 4, '.', '')) . ':"' . number_format($suboptionsqty[$lk], 4, '.', '') . '";a:4:{s:3:"qty";d:' . $suboptionsqty[$lk] . ';s:5:"price";d:' . $suboptionprice[$lk] . ';s:5:"label";s:' . strlen($suboptionslabels[$lk]) . ':"' . ucwords($suboptionslabels[$lk]) . '";s:5:"order";s:1:"' . $lk . '";}';
                            }
                            $ddqstring = $ddqstring . "}";
                            //exit;
                            $resource = Mage::getSingleton('core/resource');
                            $conn = $resource->getConnection('core_read');
                            $productId = mysql_real_escape_string($productId);
                            $ddqstring = mysql_real_escape_string($ddqstring);
                            $result = $conn->query("SELECT entity_id, attribute_id FROM `catalog_product_entity_text` WHERE attribute_id=165 and entity_id=" . $productId)->fetchAll();
                            if ($result[0]['entity_id'] == $productId && $result[0]['attribute_id'] == 165) {
                                $write2 = Mage::getSingleton('core/resource')->getConnection('core_write');
                                $write2->query("update catalog_product_entity_text set value='" . $ddqstring . "' where entity_id=" . $productId . " and attribute_id=165");
                            } else {
                                $write1 = Mage::getSingleton('core/resource')->getConnection('core_write');
                                $write1->query("INSERT INTO catalog_product_entity_text (`entity_type_id`, `attribute_id`, `store_id`, `entity_id`, `value`) VALUES ('4', '165', '0', " . $productId . ",'" . $ddqstring . "');");
                            }
                            $ddqstring = "";

                            if ($label_joutsenmerkki == 1 || $label_FSC == 1 || $label_PEFC == 1) {
                                $customattcode = "eco_friendly";
                                $m = Mage::getModel('catalog/resource_eav_attribute')
                                    ->loadByCode('catalog_product', $customattcode);
                                $customeattid = $m->getId();


                                $customval = "Kyllä";
                                $product = Mage::getModel('catalog/product')->load($productId);
                                setOrAddOptionAttributeunique($product, $customattcode, $customval);
                                $attributeoptionid = getAttributeOptionValue($customeattid, $customval);
                                $product->setData($customattcode, $attributeoptionid);
                                $product->save();
                            }

                            if ($label_joutsenmerkki == 1 || $label_FSC == 1 || $label_PEFC == 1 || $label_kukka == 1 || $label_Bra == 1) {
                                $customattcode = "ecofriendly";
                                $m = Mage::getModel('catalog/resource_eav_attribute')
                                    ->loadByCode('catalog_product', $customattcode);
                                $customeattid = $m->getId();


                                $customval = "Kyllä";
                                $product = Mage::getModel('catalog/product')->load($productId);
                                setOrAddOptionAttributeunique($product, $customattcode, $customval);
                                $attributeoptionid = getAttributeOptionValue($customeattid, $customval);
                                $product->setData($customattcode, $attributeoptionid);
                                $product->save();
                            } else {
                                $customattcode = "ecofriendly";
                                $m = Mage::getModel('catalog/resource_eav_attribute')
                                    ->loadByCode('catalog_product', $customattcode);
                                $customeattid = $m->getId();


                                $customval = "No";
                                $product = Mage::getModel('catalog/product')->load($productId);
                                setOrAddOptionAttributeunique($product, $customattcode, $customval);
                                $attributeoptionid = getAttributeOptionValue($customeattid, $customval);
                                $product->setData($customattcode, $attributeoptionid);
                                $product->save();
                            }

                            if ($logolabels != "") {
                                $attrCode = 'logolabels';

                                $sourceModel = Mage::getModel('catalog/product')->getResource()
                                        ->getAttribute($attrCode)->getSource();
                                $valuesText = explode(';', $logolabels);
                                $valuesIds = array_map(array($sourceModel, 'getOptionId'), $valuesText);
                                $product->setData($attrCode, $valuesIds);
                                $product->save();
                                $logolabels = "";
                            }


                            //related products
                            $aParams = array();
                            $nRelatedCounter = 1;

                            foreach ($aRelatedProducts as $sSku) {
                                $aRelatedProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $sSku);
                                $aParams[$aRelatedProduct['entity_id']] = array('position' => $nRelatedCounter);
                                $nRelatedCounter++;
                            }


                            $product->setRelatedLinkData($aParams);
                            $product->save();

                            if ($stockinfo > 0) {
                                $stockData = array(
                                    'manage_stock' => 0,
                                    'is_in_stock' => 1,
                                    'qty' => $stockinfo,
                                );
                            } else {
                                $stockData = array(
                                    'manage_stock' => 0,
                                    'is_in_stock' => 0,
                                    'qty' => $stockinfo,
                                );
                            }
                            $productdata = array(
                                'short_description' => trim($productshortDescription),
                                'stock_data' => $stockData,
                            );

                            if ($drinkable == true) {
                                $productdata['complimentary_product'] = "65102590";
                                $drinkable = false;
                            }

                            $result = $client->call($sessionId, 'catalog_product.update', array($productId, $productdata));
                            unset($productimages);
                            unset($categoryIds);
                            unset($suboptionsname);
                            unset($suboptions);
                            unset($categoriesnames);
                            $drinkable = false;
                            continue;
                        } else {
                            //add new product with retiened information
                            $productattachementsT = array();
                            $productattachementsK = array();
                            if ($html->find('ul.attachments', 0)) {
                                $pdsftext = $html->find('ul.attachments', 0);
                                foreach ($pdsftext->find('a') as $pdfhtml) {
                                    $path = "";
                                    $pdflink = $pdfhtml->getAttribute('href');
                                    if (trim($pdfhtml->plaintext) == "Tuotelehti") {
                                        $pdffilename = str_ireplace('http://kuvat.pamark.fi/tuotelehti/', '', trim($pdflink));
                                        $path = Mage::getBaseDir('media') . DS . 'downloads_import' . DS . '2' . DS . $pdffilename;
                                    }
                                    if (trim($pdfhtml->plaintext) == "Käyttöturvallisuustiedote") {
                                        $pdffilename = str_ireplace('http://kuvat.pamark.fi/kayttoturva/', '', trim($pdflink));
                                        $path = Mage::getBaseDir('media') . DS . 'downloads_import' . DS . '3' . DS . $pdffilename;
                                    }
                                    $pdfurl = $pdflink;

                                    $ch = curl_init($pdfurl);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_REFERER, $pdfurl);

                                    $data = curl_exec($ch);

                                    curl_close($ch);

                                    $result = file_put_contents($path, $data);

                                    if (!$result) {
                                        echo "error";
                                    } else {
                                        echo "success";
                                    }
                                }
                            }


                            if ($stockinfo > 0) {
                                $stockData = array(
                                    'manage_stock' => 0,
                                    'is_in_stock' => 1,
                                    'qty' => $stockinfo,
                                );
                            } else {
                                $stockData = array(
                                    'manage_stock' => 0,
                                    'is_in_stock' => 0,
                                    'qty' => $stockinfo,
                                );
                            }

                            $productdata = array(
                                'categories' => $categoryIds,
                                'websites' => array(1),
                                'name' => trim($productName),
                                'description' => trim($productDescription),
                                'short_description' => trim($productshortDescription),
                                'custom_stock_estimate' => trim($custom_stock_estimate),
                                'price' => sprintf("%0.2f", trim($productPrice)),
                                'tax_class_id' => 0,
                                'status' => '1',
                                'weight' => $productWeight,
                                'visibility' => '4',
                                'stock_data' => $stockData,
                                'supplier_product_url' => trim($produrl),
                                'meta_description' => trim($productMetaDescrition),
                                'meta_keyword' => trim($productMetaKeywords),
                                'meta_title' => trim($productMetaTitle),
                            );

                            if ($productspecialPrice > 0) {
                                $productdata['special_price'] = sprintf("%0.2f", trim($productspecialPrice));
                            }
                            if ($drinkable == true) {
                                $productdata['complimentary_product'] = "65102590";
                                $drinkable = false;
                            }

                            $result = $client->call($sessionId, 'catalog_product.create', array('simple', 4, $productSku, $productdata));


                            var_dump($result);
                            $productId = $result;
                            $ddqstring = "a:" . count($suboptionsqty) . ":{";
                            for ($lk = 0; $lk < count($suboptionsqty); $lk++) {
                                $ddqstring = $ddqstring . 's:' . strlen(number_format($suboptionsqty[$lk], 4, '.', '')) . ':"' . number_format($suboptionsqty[$lk], 4, '.', '') . '";a:4:{s:3:"qty";d:' . $suboptionsqty[$lk] . ';s:5:"price";d:' . $suboptionprice[$lk] . ';s:5:"label";s:' . strlen($suboptionslabels[$lk]) . ':"' . ucwords($suboptionslabels[$lk]) . '";s:5:"order";s:1:"' . $lk . '";}';
                            }
                            $ddqstring = $ddqstring . "}";
                            $productId = mysql_real_escape_string($productId);
                            $ddqstring = mysql_real_escape_string($ddqstring);
                            $resource = Mage::getSingleton('core/resource');
                            $conn = $resource->getConnection('core_read');
                            $result = $conn->query("SELECT entity_id, attribute_id FROM `catalog_product_entity_text` WHERE attribute_id=165 and entity_id=" . $productId)->fetchAll();
                            if ($result[0]['entity_id'] == $productId && $result[0]['attribute_id'] == 165) {
                                $write2 = Mage::getSingleton('core/resource')->getConnection('core_write');
                                $write2->query("update catalog_product_entity_text set value='" . $ddqstring . "' where entity_id=" . $productId . " and attribute_id=165");
                            } else {
                                $write1 = Mage::getSingleton('core/resource')->getConnection('core_write');
                                $write1->query("INSERT INTO catalog_product_entity_text (`entity_type_id`, `attribute_id`, `store_id`, `entity_id`, `value`) VALUES ('4', '165', '0', " . $productId . ",'" . $ddqstring . "');");
                            }

                            if ($label_joutsenmerkki == 1 || $label_FSC == 1 || $label_PEFC == 1 || $label_kukka == 1 || $label_Bra == 1) {
                                $customattcode = "ecofriendly";
                                $m = Mage::getModel('catalog/resource_eav_attribute')
                                    ->loadByCode('catalog_product', $customattcode);
                                $customeattid = $m->getId();
                                //$customattcode=$attcode;


                                $customval = "Kyllä";
                                $product = Mage::getModel('catalog/product')->load($productId);
                                setOrAddOptionAttributeunique($product, $customattcode, $customval);
                                $attributeoptionid = getAttributeOptionValue($customeattid, $customval);
                                $product->setData($customattcode, $attributeoptionid);
                                $product->save();
                            }


                            $countimg = 0;
                            foreach ($productimages as $imagePath) {
                                $filename = $productSku;


                                if ($countimg == 0) {
                                    $newImage = array(
                                        'file' => array(
                                            'name' => $filename,
                                            'content' => base64_encode(file_get_contents($imagePath)),
                                            'mime' => 'image/jpeg'
                                        ),
                                        'label' => trim($productName),
                                        'position' => $countimg,
                                        'types' => array('image', 'small_image', 'thumbnail'),
                                        'exclude' => 0
                                    );
                                } else {
                                    $newImage = array(
                                        'file' => array(
                                            'name' => $filename,
                                            'content' => base64_encode(file_get_contents($imagePath)),
                                            'mime' => 'image/jpeg'
                                        ),
                                        'label' => trim($productName),
                                        'position' => $countimg,
                                        'types' => null,
                                        'exclude' => 0
                                    );
                                }
                                if (getimagesize($imagePath) !== false) {
                                    $imageFilename = $client->call($sessionId, 'product_media.create', array($productId, $newImage));
                                }
                                $countimg++;
                            }

                            if ($logolabels != "") {
                                $attrCode = 'logolabels';
                                $sourceModel = Mage::getModel('catalog/product')->getResource()
                                        ->getAttribute($attrCode)->getSource();
                                $valuesText = explode(';', $logolabels);
                                print_r($valuesText);
                                $valuesIds = array_map(array($sourceModel, 'getOptionId'), $valuesText);
                                $product = Mage::getModel('catalog/product')->load($productId);
                                $product->setData($attrCode, $valuesIds);
                                $product->save();
                                $logolabels = "";
                            }


                            if ($proddistributer != "") {
                                $customattcode = "manufacturer";
                                $m = Mage::getModel('catalog/resource_eav_attribute')
                                    ->loadByCode('catalog_product', $customattcode);
                                $customeattid = $m->getId();


                                $customval = trim($proddistributer);
                                $product = Mage::getModel('catalog/product')->load($productId);
                                setOrAddOptionAttributeunique($product, $customattcode, $customval);
                                $attributeoptionid = getAttributeOptionValue($customeattid, $customval);
                                $product->setData($customattcode, $attributeoptionid);
                                $product->save();
                            }

                            $url = Mage::getModel('catalog/product')->loadByAttribute('sku', $productSku)->getProductUrl();
                        }
                    }




                    unset($productimages);
                    unset($categoryIds);
                    unset($suboptionsname);
                    unset($suboptions);
                    unset($categoriesnames);
                    unset($suboptionsqty);
                    unset($suboptionslabels);
                    unset($suboptionprice);
                    $drinkable = false;
                }

                fclose($fp);
            }
        }

        function getAttributeOptionValue($configurable_attribute, $attr_value)
        {
            $resource = Mage::getSingleton('core/resource');
            $read = $resource->getConnection('catalog_read');
            $attrVal = mysql_real_escape_string($attr_value);
            $CurtAtr = mysql_real_escape_string($configurable_attribute);
            $result = $read->fetchAll("SELECT ao.option_id FROM eav_attribute_option_value as aov, eav_attribute_option as ao where value='$attrVal' and aov.option_id = ao.option_id and attribute_id='$CurtAtr' limit 1");
            return($result[0]['option_id']);
        }

        function addAttributeOption($configurable_attribute, $attr_value)
        {
            $installer = new Mage_Eav_Model_Entity_Setup('core_setup');
            $installer->startSetup();
            $ProductEntityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
            $Option = array();
            $Option['attribute_id'] = $installer->getAttributeId($ProductEntityTypeId, $configurable_attribute);
            $Option['value']['option'][0] = $attr_value;
            $installer->addAttributeOption($Option);
            $installer->endSetup();
        }

        function setOrAddOptionAttributeunique($product, $arg_attribute, $arg_value)
        {
            $attribute_model = Mage::getModel('eav/entity_attribute');
            $attribute_options_model = Mage::getModel('eav/entity_attribute_source_table');

            $attribute_code = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
            $attribute = $attribute_model->load($attribute_code);

            $attribute_options_model->setAttribute($attribute);
            $options = $attribute_options_model->getAllOptions(false);

            // determine if this option exists
            $value_exists = false;
            foreach ($options as $option) {
                if ($option['label'] === $arg_value) {
                    $value_exists = true;
                    break;
                }
            }

            // if this option does not exist, add it.
            if (!$value_exists) {
                $attribute->setData('option', array(
                    'value' => array(
                        'option' => array($arg_value, $arg_value)
                    )
                ));

                $attribute->save();
            }

            //$product->setData($arg_attribute,$arg_value);
        }

        function setOrAddOptionAttribute($product, $arg_attribute, $arg_value)
        {
            $attribute_model = Mage::getModel('eav/entity_attribute');
            $attribute_options_model = Mage::getModel('eav/entity_attribute_source_table');

            $attribute_code = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
            $attribute = $attribute_model->load($attribute_code);

            $attribute_options_model->setAttribute($attribute);
            $options = $attribute_options_model->getAllOptions(false);

            // determine if this option exists
            $value_exists = false;
            foreach ($options as $option) {
                if ($option['label'] === $arg_value) {
                    $value_exists = true;
                    break;
                }
            }

            // if this option does not exist, add it.
            if (!$value_exists) {
                $attribute->setData('option', array(
                    'value' => array(
                        'option' => array($arg_value, $arg_value)
                    )
                ));
                $attribute->save();
            }
        }

        function multiArrayValueSearch($haystack, $needle, &$result, &$aryPath = NULL, $currentKey = '')
        {
            if (is_array($haystack)) {
                $count = count($haystack);
                $iterator = 0;
                foreach ($haystack as $location => $straw) {
                    $iterator++;
                    $next = ($iterator == $count) ? false : true;
                    if (is_array($straw)) {
                        $aryPath[$location] = $location;
                    }
                    multiArrayValueSearch($straw, $needle, $result, $aryPath, $location);
                    if (!$next) {
                        unset($aryPath[$currentKey]);
                    }
                }
            } else {
                $straw = $haystack;
                if ($straw == $needle) {
                    if (!isset($aryPath)) {
                        $strPath = "\$result[$currentKey] = \$needle;";
                    } else {
                        $strPath = "\$result['" . join("']['", $aryPath) . "'][$currentKey] = \$needle;";
                    }
                    eval($strPath);
                }
            }
        }

        function createCategory($parentId, $name, $mode)
        {
            $category = new Mage_Catalog_Model_Category();
            $category->setStoreId(Mage::app()->getStore()->getId());

            $category->setName($name);
            $category->setIsActive(1);
            $category->setDisplayMode($mode);
            $category->setIsAnchor(1);

            if (!$parentId) {
                $parentId = Mage::app()->getStore($storeId)->getRootCategoryId();
            }
            $parentCategory = Mage::getModel('catalog/category')->load($parentId);
            $category->setPath($parentCategory->getPath());

            $category->setAttributeSetId($category->getDefaultAttributeSetId());
            $category->save();
            $catId = $category->getId();
            unset($category);

            return $catId;
        }

        function is_producturl($string)
        {
            $htmlpcheck = file_get_html($string);
            if (is_object($htmlpcheck)) {
                if ($htmlpcheck->find('input[id=unit-qty]', 0)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        function deleteProductCustomOption($product_id, $option_title)
        {
            $product = Mage::getModel('catalog/product')->load($product_id);
            $options = $product->getProductOptionsCollection();
            if (isset($options)) {
                foreach ($options as $o) {
                    if ($o->getTitle() == $option_title) {
                        $o->delete();
                    }
                }
            }
        }

        ?>

    </body>
</html>