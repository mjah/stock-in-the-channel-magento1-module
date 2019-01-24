<?php

class Bintime_Sinchimport_Model_Layer_Filter_Feature extends Mage_Catalog_Model_Layer_Filter_Abstract
{
    protected $_resource;

    protected $dont_panic = true;

    const LESS = 1;
    const GREATER = 2;
    /**
     * Construct attribute filter
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->_requestVar = 'ice_feature';
    }

    /**
     * Задаёт атрибут и строку запроса для текущего фильтра
     * @param Фича из Icecat'a
     * @return Bintime_Icelayered_Model_Layer_Filter_Feature
     */
    public function setAttributeModel($attribute)
    {
        $this->setRequestVar('feature_' . $attribute['category_feature_id']);
        $this->setData('attribute_model', $attribute);
        return $this;
    }

    public function getName()
    {
        $attribute = $this->getAttributeModel();
        return $attribute['name'];
    }

    /**
     * Retrieve resource instance
     *
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Layer_Filter_Attribute
     */
    protected function _getResource()
    {

        if (is_null($this->_resource)) {
            $this->_resource = Mage::getResourceModel('sinchimport/layer_filter_feature');
        }
        return $this->_resource;
    }

    /**
     * Get option text from frontend model by option id
     *
     * @param   int $optionId
     * @return  unknown
     */
    protected function _getOptionText($optionId)
    {
        $feature = $this->getAttributeModel();
        return $optionId;
    }

    /**
     * Apply attribute option filter to product collection
     *
     * @param   Zend_Controller_Request_Abstract $request
     * @param   Varien_Object $filterBlock
     * @return  Mage_Catalog_Model_Layer_Filter_Attribute
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        $filter = $request->getParam($this->_requestVar);
        if (is_array($filter)) {
            return $this;
        }

        $text = $this->_getOptionText($filter);
        if ($filter && $text) {
            $this->_getResource()->applyFilterToCollection($this, $filter);
            $this->getLayer()->getState()->addFilter($this->_createItem($text, $filter));
            $this->_items = array();
        }

        return $this;
    }

    /**
     * Check whether specified attribute can be used in LN
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return bool
     */
    protected function _getIsFilterableAttribute($attribute)
    {
        if ($this->dont_panic) return null;
        throw new Exception(__METHOD__);
        return $attribute->getIsFilterable();
    }

    /**
     * Возвращает массив с информацией по опциям фильтра.
     * @return array
     */
    protected function _getItemsData()
    {
        Varien_Profiler::start(__METHOD__);

        $feature = $this->getAttributeModel();
        $this->_requestVar = 'feature_' . $feature['category_feature_id'];
        $limitDirection = isset($feature['limit_direction']) ? $feature['limit_direction'] : 0;

        $data = array();
        $options = explode("\n", $feature['restricted_values']);
		if (count($options) == 0) {
			Varien_Profiler::stop(__METHOD__);
			return $data;
		}
		if (isset($feature['order_val']) && $feature['order_val'] == '2') {
			$options = array_reverse($options);
		}
		if ($limitDirection != self::LESS && $limitDirection != self::GREATER) {

			$optionsCount = $this->_getResource()->getCount($this);
			foreach ($options as $option) {
				if (($pos = strpos($option, '::')) !== false) {
					$value = substr($option, 0, $pos);
					$presentation_value = substr($option, $pos + 2);
				}
				else {
					$value = $presentation_value = $option;
				}
				if (isset($optionsCount[$value]) && $optionsCount[$value] > 0) {
				   $data[] = array(
						'label' => $presentation_value,
						'value' => $value,
						'count' => $optionsCount[$value],
					);
				}
			}
		} else {
			$oCount = count($options);
			$intervals = array();
			if ($feature['order_val'] == '2') {
				for ($i = 0; $i < $oCount -1; $i++) {
					$intervals[$i]['high'] = $options[$i];
					$intervals[$i]['low'] = $options[$i +1];
				}
			} else {
				for ($i = 0; $i < $oCount -1; $i++) {
					$intervals[$i]['low'] = $options[$i];
					$intervals[$i]['high'] = $options[$i +1];
				}
			}
			//FIXME: this is ugly
			if ($feature['order_val'] == '2') {
				array_push ($intervals, array(
					'high' => $options[$oCount -1],
				));
				array_unshift($intervals, array(
					'low' => $options[0],
				));
			} else {
				array_push ($intervals, array(
					'low' => $options[$oCount -1],
				));
				array_unshift($intervals, array(
					'high' => $options[0],
				));
			}

			$this->setData('intervals', $intervals);

			$defaultSign = $feature['default_sign'];
			for($i = 0; $i < count($intervals); $i++) {
				if ($feature['order_val'] == '2') {
					$interval = $intervals[$i];
					$label = isset($interval['high']) ? $interval['high'] . " $defaultSign" : '>';
					if ($label == '>' && isset($intervals[$i + 1])) {
						$pad = strlen($intervals[$i + 1]['high'] . $defaultSign) + 2;
						$label = str_pad($label, $pad*2, ' ', STR_PAD_LEFT);
						$label = str_replace(' ', '&nbsp', $label);
					}
					$label .= isset($interval['high'], $interval['high']) ? ' - ' : ' ';
					$label .= isset($interval['low']) ? $interval['low'] . " $defaultSign" : '>';
					$value = isset($interval['low']) ?  $interval['low'] : '-';
					$value .= ',';
					$value .= isset($interval['high']) ?  $interval['high'] : '-';
					if (isset($interval['high']) AND !isset($interval['low'])) { $value = '-,'.$interval['high'];}
					if ($this->_getResource()->getIntervalsCountDescending($this, $interval) > 0){
						$data[] = array(
							'label' => $label,
							'value' => $value,
							'count' => $this->_getResource()->getIntervalsCountDescending($this, $interval),
						);
					}
				} else {
					$interval = $intervals[$i];
					$label = isset($interval['low']) ? $interval['low'] . " $defaultSign" : '<';
					if ($label == '<' && isset($intervals[$i + 1])) {
						$pad = strlen($intervals[$i + 1]['low'] . $defaultSign) + 2;
						$label = str_pad($label, $pad*2, ' ', STR_PAD_LEFT);
						$label = str_replace(' ', '&nbsp', $label);
					}
					$label .= isset($interval['low'], $interval['high']) ? ' - ' : ' ';
					$label .= isset($interval['high']) ? $interval['high'] . " $defaultSign" : '<';

					$value = isset($interval['low']) ?  $interval['low'] : '-';
					$value .= ',';
					$value .= isset($interval['high']) ?  $interval['high'] : '-';
					if ($this->_getResource()->getIntervalsCount($this, $interval) > 0){
						$data[] = array(
							'label' => $label,
							'value' => $value,
							'count' => $this->_getResource()->getIntervalsCount($this, $interval),
						);
					}
				}
			}
		}

        Varien_Profiler::stop(__METHOD__);
        return $data;
    }

    /*public function getOrderValues($category_feature_id,$categoryId)
    {
        $select = "
            SELECT COUNT(e.entity_id) AS count
            FROM ".Mage::getSingleton('core/resource')->getTableName('catalog_product_entity')." AS e
            INNER JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_category_product_index')." AS cat_index
                ON cat_index.product_id=e.entity_id
                AND cat_index.store_id='1'
                AND cat_index.visibility IN(2, 4)
                AND cat_index.category_id='".$categoryId."'
            INNER JOIN ".Mage::getSingleton('core/resource')->getTableName('catalog_product_index_price')." AS price_index
                ON price_index.entity_id = e.entity_id
                AND price_index.website_id = '1'
                AND price_index.customer_group_id = 0
            INNER JOIN ".Mage::getSingleton('core/resource')->getTableName('stINch_products_feature_'.$feature['category_feature_id'])." AS idx_".$category_feature_id."
                ON idx_".$category_feature_id.".entity_id = e.icecat_product_id;
        ";

        return Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol($select);
    }*/

}
