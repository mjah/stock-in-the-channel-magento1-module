<?php
class Bintime_Sinchimport_Block_List extends Mage_Catalog_Block_Product_Compare_List
{
	var	$goods;
	var 	$_compareSinchStoreProd;
	 public function getSinchAttributes()
	 {
	     $items = array();
		  foreach ($this->getItems() as $_item) {
		      $items[] = $_item->getId();
		  }
		  $to_compare=implode(',', $items);	
  $tmp_table_sorted = Mage::getSingleton('core/resource')->getTableName('$tmp_table_sorted_').time();
  $this->tep_db_query("DROP TABLE IF EXISTS $tmp_table_sorted");
  $this->tep_db_query("CREATE TABLE $tmp_table_sorted (
                                                                id                      int(11) not null PRIMARY KEY AUTO_INCREMENT,
                                                                store_category_id       int(11) not null default 0,
                                                                store_product_id        int(11) not null default 0,
                                                                sinch_product_id        int(11) not null default 0,
                                                                category_feature_id     int(11) not null default 0,
                                                                feature_name            varchar(255) not null default '',
                                                                restricted_value_id     int(11) not null default 0,
                                                                text                    varchar(255) not null default '',
                                                                KEY(category_feature_id),
                                                                KEY(store_product_id),
                                                                                                                                                                         unique key(sinch_product_id, feature_name)
                                                              )");


  $query = "SELECT p.sinch_product_id, p.store_product_id, p.specifications, pm.entity_id
            FROM  ".Mage::getSingleton('core/resource')->getTableName('stINch_products')." p
            JOIN  ".Mage::getSingleton('core/resource')->getTableName('stINch_products_mapping')." pm 
                ON  pm.shop_store_product_id = p.store_product_id AND 
                    pm.shop_sinch_product_id=p.sinch_product_id
            WHERE pm.entity_id IN ( $to_compare )";
  $sinch = $this->tep_db_query($query);
        while($data =  $sinch->fetch(PDO::FETCH_ASSOC)){
                $this->ins_from_htm($data['sinch_product_id'],$data['store_product_id'],$data['specifications'],$tmp_table_sorted);
                $sinch_products_array[] = $data['sinch_product_id'];
		$this->_compareSinchStoreProd[$data['entity_id']]=$data['sinch_product_id'];
        }
   $sinch_products = implode(',', $sinch_products_array);
   if(!$sinch_products){	
	   $sinch_products = "''";
   }	
   $this->tep_db_query("INSERT IGNORE INTO $tmp_table_sorted (
                            store_category_id, 
                            sinch_product_id, 
                            category_feature_id, 
                            feature_name, 
                            restricted_value_id, 
                            text
                        )(SELECT 
                            cf.store_category_id, 
                            pf.sinch_product_id, 
                            cf.category_feature_id, 
                            cf.feature_name, 
                            rv.restricted_value_id, 
                            rv.text 
                          FROM ".Mage::getSingleton('core/resource')->getTableName('stINch_categories_features')." cf 
                          JOIN ".Mage::getSingleton('core/resource')->getTableName('stINch_restricted_values')." rv 
                            ON cf.category_feature_id=rv.category_feature_id 
                          JOIN ".Mage::getSingleton('core/resource')->getTableName('stINch_product_features')." pf 
                            ON rv.restricted_value_id=pf.restricted_value_id 
                          WHERE sinch_product_id in (".$sinch_products.") 
                          ORDER BY 
                            cf.display_order_number, 
                            pf.sinch_product_id)
                      ");
   $this->tep_db_query("UPDATE $tmp_table_sorted t 
                        JOIN ".Mage::getSingleton('core/resource')->getTableName('stINch_products')." p 
                            ON t.sinch_product_id=p.sinch_product_id 
                        SET t.store_product_id=p.store_product_id");

        $this->goods = array();

        $product_query = $this->tep_db_query("select store_product_id, sinch_product_id, category_feature_id, store_category_id, feature_name, text
                                         FROM    $tmp_table_sorted
                                                ORDER BY id ASC");
                while($data = $product_query->fetch(PDO::FETCH_ASSOC)){
                        $this->goods[$data['sinch_product_id']]['category_features'][$data['feature_name']]   = array(


                                                                          'category_feature_id' =>        $data['category_feature_id'],
                                                                                                                                                                                                                                                                                                                                                                                        'value'                 =>      $data['text'],


                                                                          'feature_name'          =>      $data['feature_name'],
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                );
                }
		$catfeats = $this->tep_db_query("SELECT category_feature_id, feature_name   FROM $tmp_table_sorted
                                                                                     GROUP BY feature_name
                                                                                     ORDER BY category_feature_id");
                        while($fdata = $catfeats->fetch(PDO::FETCH_ASSOC)){
                                $cf[] = $fdata;
			}
		return $cf;
	 }
	
	 public function GetSinchCompareProdAttributes(){
		return $this->goods;
	 }

	 public function getSinchAttributeName($cf)
	 {
		return $cf['feature_name'];
	 }
    
	 public function getSinchProductAttributeValue($product, $cf)
	 {
		
	    $prod = $this->_compareSinchStoreProd[$product->getID()];
	    $feature_name = $cf['feature_name'];	
	    $data = $this->goods[$prod]['category_features'][$feature_name]['value'];
	    $data = Mage::getModel('sinchimport/sinch')->valid_utf($data);	
         return $data;
	 }

	 private function ins_from_htm($sinch_product_id, $store_product_id, $htm, $tmp_table_sorted){
		 if($htm){
			 /** создаем новый dom-объект **/
			 $dom = new domDocument;
			 /** загружаем html в объект **/
			 $dom->loadHTML($htm);
			 $dom->preserveWhiteSpace = false;

			 /** элемент по тэгу **/
			 $tables = $dom->getElementsByTagName('table');

			 /** получаем все строки таблицы **/
			 $rows = $tables->item(0)->getElementsByTagName('tr');

			 /** цикл по строкам **/
			 $i=0;
			 foreach ($rows as $row)
			 {
				 /** все ячейки по тэгу **/
				 $cols = $row->getElementsByTagName('td');
				 /** выводим значения **/
				 $name = $cols->item(0)->nodeValue;
				 $value = $cols->item(1)->nodeValue;

				 if($value && $i){
					 $q=" insert ignore into ".$tmp_table_sorted." (store_product_id, sinch_product_id, feature_name, text)	 values(".$store_product_id.",".$sinch_product_id.",'".$name."','".$value."')";
					 $this->tep_db_query($q);
				 		}
//mysqli_real_escape_string($name)
				 $i++;
			 }
		 }
	 }
	 private function tep_db_query($q){
		$resource = Mage::getResourceSingleton('catalog/product');
		$connection = $resource->getReadConnection();
                $result = $connection->query($q);
		return($result);		
	 }	
    
}
