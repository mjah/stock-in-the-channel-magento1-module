<?php
if ($argc != 2) {
    throw new Exception(PHP_EOL . 'Use: php storeProcedureGenerator.php tablePrefix' . PHP_EOL);
}
$prefix = $argv[1];
$file_template = "filter_sinch_products_s.sql";
$new_store_file = $prefix.$file_template;
$table_array=array(
"filter_sinch_products_s",
"tmp_result",
"catalog_product_entity",
"catalog_category_product_index",
"stINch_categories_mapping",
"stINch_categories_features",
"stINch_products",
"stINch_product_features",
"stINch_restricted_values",
"FilterListOfFeatures",
"SinchFilterResult_"
);
$new_table_array = array();
foreach($table_array as $key => $table){
    $new_table_array[] = $prefix.$table;
    $table_array[$key] = "/".$table_array[$key]."/";
}
$file = file("$file_template");
$cahnged_file = fopen($new_store_file, "a+");
foreach($file as $str) {
    $changed_string  = preg_replace($table_array, $new_table_array, $str);
    fwrite($cahnged_file, $changed_string);
}
fclose($cahnged_file);
//echo "\nNew store file (".$new_store_file .") generated\n";
