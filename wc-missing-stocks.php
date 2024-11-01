<?php
/*
Plugin Name: WC Missing Stocks
Plugin URI: http://getsocialshops.com/missingstocks
Description: WC Missing Stocks plugin creates a report of missing stocks.
Tags: missingstocks,stocksreport,report,stocks,stockreports,reports,woocommerce,woocommercereports
Version: 1.0
Author: Kamran Akhtar (http://techfas.com/)
*/

global $wpdb;

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WcMissingStocks_List_Table extends WP_List_Table {
	
    var $wcmissingstocks_data;
	
    function __construct(){
        global $status, $page, $wpdb, $woocommerce;
		
		$date1 = '';
		$date2 = '';
		$daterange = '';
		$searchStr = '';
		if(isset($_REQUEST['wcmissingstocks-submit']))
		{
			if(isset($_REQUEST['wcmissingstocks-daterange-filter']))
			{
				$date = explode(" - ",sanitize_text_field($_REQUEST['wcmissingstocks-daterange']));
				$date1 = $date[0];
				$date2 = $date[1];
				$daterange = " AND p.post_date BETWEEN '".date("Y-m-d",strtotime($date1))." 00:00:00' AND '".date("Y-m-d",strtotime($date2))." 23:59:59'";
			}
			
			if($_REQUEST['wcmissingstocks-search']!='')
			{
				$searchStr = sanitize_text_field($_REQUEST['wcmissingstocks-search']);
			}
			
		}
		
		$allOrders = array();
		
		$sql = "SELECT pm.meta_value AS user_id, pm.post_id AS order_id FROM wp_postmeta AS pm LEFT JOIN wp_posts AS p ON pm.post_id = p.ID WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-processing' AND pm.meta_key = '_customer_user'".$daterange." ORDER BY pm.meta_value ASC, pm.post_id DESC";
		
		$orders = $wpdb->get_results($wpdb->prepare($sql, $daterange));
		
		$allitems = array();
		$allproductids = array();
		$k=0;
		
		if(count($orders)>0)
		{
			foreach($orders as $order)
			{
				$odr = wc_get_order($order->order_id);
				
				foreach ($odr->get_items() as $item_id => $item_data) {
					
					$product = $item_data->get_product();
					$product_id = $product->get_id();
					if($product->is_type('variation'))
					{
						//echo $product_id;
						$p_id = $product->get_parent_id();
						$_product = wc_get_product( $p_id );
						if(!in_array($p_id,$allproductids))
						{
							$allproductids[$k] = $p_id;
							$product_name = $_product->get_name();
							$item_quantity = $item_data->get_quantity();
							$item_total = $item_data->get_total();
							
							$allitems[$p_id]['name'] = $product_name;
							$allitems[$p_id]['item_qty'] = 0;
							$allitems[$p_id]['order_total'] = $item_total;
							$allitems[$p_id]['item_price'] = $_product->get_price();
							$allitems[$p_id]['item_stock'] = 0;
							$allitems[$p_id]['variations'] = array();
							$k++;
						}
						
						if(!in_array($product_id,$allitems[$p_id]['variations']))
						{
							$product_name = $product->get_name();
							$item_quantity = $item_data->get_quantity();
							$item_total = $item_data->get_total();
							$stock = $product->get_stock_quantity();
							if($stock<0)
							{
								$stock = 0;
							}
							else
							{
								$stock = $stock;	
							}
							
							$rem_qty = $stock-$item_quantity;
							
							$v_arr = array();
							$v_arr['name'] = $product_name;
							$v_arr['item_qty'] = $item_quantity;
							$v_arr['order_total'] = $item_total;
							$v_arr['item_price'] = $product->get_price();
							$v_arr['item_stock'] = $stock;
							
							$allitems[$p_id]['item_qty'] = $allitems[$p_id]['item_qty']+$item_quantity;
							$allitems[$p_id]['item_stock'] = $allitems[$p_id]['item_stock'] + abs($rem_qty);
							
							$allitems[$p_id]['variations'][$product_id] = $v_arr;
							$k++;
						}
						else
						{	
							$stock = $product->get_stock_quantity();
							if($stock<0)
							{
								$stock = 0;
							}
							else
							{
								$stock = $stock;	
							}
							
							$item_quantity = $item_data->get_quantity();
							
							$rem_qty = $stock-$item_quantity;
							$allitems[$p_id]['item_stock'] = $allitems[$p_id]['item_stock'] + $stock;
							$allitems[$p_id]['item_qty'] = $allitems[$p_id]['item_qty'] + $item_quantity;
							$allitems[$p_id]['variations'][$product_id]['item_qty'] = $allitems[$p_id]['variations'][$product_id]['item_qty'] + $item_quantity;
						}
						
					}
					else
					{
						
						if(!in_array($product_id,$allproductids))
						{
							
							$allproductids[$k] = $product_id;
							
							$product_name = $product->get_name();
							$item_quantity = $item_data->get_quantity();
							$item_total = $item_data->get_total();
							$stock = $product->get_stock_quantity();
							if($stock<0)
							{
								$stock = 0;
							}
							else
							{
								$stock = $stock;	
							}
							
							$allitems[$product_id]['name'] = $product_name;
							$allitems[$product_id]['item_qty'] = $item_quantity;
							$allitems[$product_id]['order_total'] = $item_total;
							$allitems[$product_id]['item_price'] = $product->get_price();
							$allitems[$product_id]['item_stock'] = $stock;
							$allitems[$product_id]['variations'] = array();
							$k++;
						}
						else
						{	
							
							$item_quantity = $item_data->get_quantity();
							$allitems[$product_id]['item_qty'] = $allitems[$product_id]['item_qty'] + $item_quantity;
						}
					
					}
					
				}
				
				
			}
		}
		
		$z=0;
		
		if($allitems)
		{
			foreach($allitems as $itm_id=>$itm)
			{	
				$product = wc_get_product($itm_id);
				
				$title = get_the_title($itm_id);
				
				if($searchStr!='')
				{
					if (strpos($title, $searchStr) !== false) {
					}
					else
					{
						continue;
					}
				}
				
				$base_index = $z;
				
				$z++;
				
				$stockIs = 0;
				if(!empty($itm['variations']))
				{
					foreach($itm['variations'] as $var_id=>$var)
					{
						$_product = wc_get_product($var_id);
						
						$stock = $_product->get_stock_quantity();
						if($stock<0)
						{
							$stock = 0;
						}
						else
						{
							$stock = $stock;	
						}
						
						$title = get_the_title($var_id);
						
						$rem_qty = $stock-$var['item_qty'];
						$remains = $rem_qty<0?abs($rem_qty):0;
						if($remains>0)
						{	
							$stockIs+=$remains;
							$allOrders[$z]['ID'] = $var_id;
							$allOrders[$z]['wc_missing_stocks_sku'] = $_product->get_sku();
							$allOrders[$z]['wc_missing_stocks_image'] = get_the_post_thumbnail_url($var_id);
							$allOrders[$z]['wc_missing_stocks_name'] = "-- ".$title;
							$allOrders[$z]['wc_missing_stocks_cost'] = $_product->get_price();
							$allOrders[$z]['wc_missing_stocks_stock'] = $remains;
							$z++;
						}
						
					}
				}
				
				if(!empty($itm['variations']))
				{
					if($stockIs>0)
					{
						$allOrders[$base_index]['ID'] = $itm_id;
						$allOrders[$base_index]['wc_missing_stocks_sku'] = $product->get_sku();
						$allOrders[$base_index]['wc_missing_stocks_image'] = get_the_post_thumbnail_url($itm_id);
						$allOrders[$base_index]['wc_missing_stocks_name'] = get_the_title($itm_id);
						$allOrders[$base_index]['wc_missing_stocks_cost'] = $product->get_price();
						$allOrders[$base_index]['wc_missing_stocks_stock'] = $stockIs;
					}
				}
				else
				{
					
					$rem_qty = $itm['item_stock']-$itm['item_qty'];
					if($stockIs>0)
					{
						$allOrders[$base_index]['ID'] = $itm_id;
						$allOrders[$base_index]['wc_missing_stocks_sku'] = $product->get_sku();
						$allOrders[$base_index]['wc_missing_stocks_image'] = get_the_post_thumbnail_url($itm_id);
						$allOrders[$base_index]['wc_missing_stocks_name'] = get_the_title($itm_id);
						$allOrders[$base_index]['wc_missing_stocks_cost'] = $product->get_price();
						$allOrders[$base_index]['wc_missing_stocks_stock'] = $rem_qty<0?abs($rem_qty):0;
					}
				}
				
			}
		}
		
		ksort($allOrders);
		
		$this->wcmissingstocks_data = $allOrders;
		
        parent::__construct( array(
            'singular'  => 'wcmissingstocks_order',
            'plural'    => 'wcmissingstocks_orders',
            'ajax'      => false 
        ) );        
    }


    function column_default($item, $column_name){
		return $item[$column_name];
    }

    function column_wc_missing_stocks_sku($item){
		$url = get_permalink($item['ID']) ;
        $actions = array(
			'view'      => sprintf('<a href="'.$url.'">View</a>',sanitize_text_field($_REQUEST['page']),'view',$item['ID']),
        );
        return sprintf('%1$s <span style="color:silver">(Order id:%2$s)</span>%3$s',
            $item['wc_missing_stocks_sku'],
            $item['ID'],
            $this->row_actions($actions)
        );
    }
	
	function column_wc_missing_stocks_image($item){
       return '<img src="'.$item['wc_missing_stocks_image'].'" style="width: 40px;border-radius: 5px;" />';
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],
            $item['ID']
        );
    }

    function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'wc_missing_stocks_sku'     => __( 'SKU', 'wcmissingstocks' ),
            'wc_missing_stocks_image'    => __( 'Feature Image', 'wcmissingstocks' ),
            'wc_missing_stocks_name'   => __( 'Name', 'wcmissingstocks' ),
			'wc_missing_stocks_cost'   => __( 'Wc Cog Cost', 'wcmissingstocks' ),
			'wc_missing_stocks_stock'     => __( 'Stock Missing', 'wcmissingstocks' ),
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'wc_missing_stocks_sku'     => array('wc_missing_stocks_sku',true),
            'wc_missing_stocks_image'    => array('wc_missing_stocks_image',true),
            'wc_missing_stocks_name'  => array('wc_missing_stocks_name',true),
			'wc_missing_stocks_cost'     => array('wc_missing_stocks_cost',true),
			'wc_missing_stocks_stock'     => array('wc_missing_stocks_stock',true)
        );
        return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
        );
        return $actions;
    }

    function prepare_items() {
        global $wpdb;

        $per_page = 20;
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = false;
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        $data = $this->wcmissingstocks_data;

        function usort_reorder($a,$b){
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'wc_missing_stocks_name';
            $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'asc';
            $result = strcmp($a[$orderby], $b[$orderby]);
            return ($order==='asc') ? $result : -$result;
        }
        
        $current_page = $this->get_pagenum();
        
        $total_items = count($data);
        
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);

        $this->items = $data;
		
        $this->set_pagination_args( array(
            'total_items' => $total_items,    
            'per_page'    => $per_page,    
            'total_pages' => ceil($total_items/$per_page)
        ) );
    }


}

add_filter('woocommerce_admin_reports','wc_missing_stocks_custom_tab');

function wc_missing_stocks_custom_tab($reports)
{
	
	$wc_missing_stocks_stock_missing = array(
        'wc_missing_stocks_stock_missing' => array(
            'title'         => 'Missing Stock',
            'description'   => '',
            'hide_title'    => true,
            'callback'      => 'wc_missing_stocks_stock_missing',
        ),
    );
	
	$reports['stock']['reports'] = array_merge( $reports['stock']['reports'], $wc_missing_stocks_stock_missing);
	
    return $reports;
}

function wc_missing_stocks_stock_missing()
{
	wp_enqueue_media();		
	
	$WcMissingStocksListTable = new WcMissingStocks_List_Table();
	$WcMissingStocksListTable->prepare_items();
	
	wp_enqueue_script('b-date-range', 'assets/daterangepicker.min.js', array(), '3', true);
	wp_enqueue_style( 'b-date-rang-css', 'assets/daterangepicker.css' );
	
	?>    

		<div class="wc-missing-stocks-main">
				
			<h1><?php echo __( 'Welcome To Missing Stocks List', 'wcmissingstocks' );?>!</h1>
			<p><?php echo __( 'Listing of all Missing Stocks', 'wcmissingstocks' );?></p>
			
			
			<h3><?php echo __( 'Missing Stocks List', 'wcmissingstocks' );?></h3>
			
			<!--<form id="wc-missing-stocks-filter" method="post">
				<input type="hidden" name="page" value="wc-missing-stocks" />
				<input type="submit" name="wcmissingstocks-export" id="wcmissingstocks-export" class="button" value="Export" style="float:right;"/>
			</form>-->
			<form id="wc-missing-stocks-filter" method="post">
				<input type="hidden" name="page" value="wc-missing-stocks" />
				<input type="text" name="wcmissingstocks-search" id="wcmissingstocks-search" placeholder="Search..."/>
				
				<input type="text" name="wcmissingstocks-daterange" id="wcmissingstocks-daterange" style="width:200px;"/>
				<br />
				<br />
				
				<label><input type="checkbox" value="1" name="wcmissingstocks-daterange-filter" id="wcmissingstocks-daterange-filter" /> Filer by date</label>
				<br />
				<br/>
				<input type="submit" name="wcmissingstocks-submit" id="wcmissingstocks-submit" class="button" value="Search" />
				<?php $WcMissingStocksListTable->display() ?>
			</form>
			
			
		</div>
		
		<script>
		jQuery(function() {
		  jQuery('input[name="wcmissingstocks-daterange"]').daterangepicker({
			opens: 'right',
		  }, function(start, end, label) {
			console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
		  });
		});
		</script>
		
	<?php

}


?>