<?php
defined('C5_EXECUTE') or die('Access Denied.');
$th = Loader::helper('text');
$c = Page::getCurrentPage();

$properties = array();

// Get attribute terms for property_type
$terms = array();
$availabilityTerms = array();
$set = AttributeSet::getByHandle('property');
$keys = $set->getAttributeKeys();
foreach($keys as $key) {
    $handle = $key->getAttributeKeyHandle();
    if( $handle == 'property_type' ) {
        $type = $key->getAttributeType();
        $cont = $type->getController();
        $cont->setAttributeKey($key);
        $terms = $cont->getOptions();
    }
    if( $handle == 'property_availability' ) {
        $type = $key->getAttributeType();
        $cont = $type->getController();
        $cont->setAttributeKey($key);
        $availabilityTerm = $cont->getOptions();
    }
};

// build filter option
$filter_options = '<select name="property_type_filter" id="property_type_filter">';
// default empty option
$filter_options .= '<option value=""></option>';
foreach($terms as $term) {
    $filter_options .= '<option value="'.trim($term).'">'.trim($term).'</option>';
}
$filter_options .= '</select>';

$filter_availability_options = '<select name="property_availability_filter" id="property_availability_filter">';
// default empty option
foreach($availabilityTerm as $term) {
    $filter_availability_options .= '<option value="'.trim($term).'">'.trim($term).'</option>';
}
$filter_availability_options .= '</select>';
?>
<script>



$(document).ready(function(){
  const pageListMap<?php echo $c->getCollectionID(); ?> = new PageListMap(<?php echo $c->getCollectionID(); ?>);

  delayMapMarkers(500).then(() => {
  <?php 
  foreach ($pages as $page){
    $title = $th->entities($page->getCollectionName());
    $url = $nh->getLinkToCollection($page);
    $target = ($page->getCollectionPointerExternalLink() != '' && $page->openCollectionPointerExternalLinkInNewWindow()) ? '_blank' : $page->getAttribute('nav_target');
    $target = empty($target) ? '_self' : $target;
    $thumbnail = $page->getAttribute('thumbnail');
    $hoverLinkText = $title;
    $description = $page->getCollectionDescription();
    $description = $controller->truncateSummaries ? $th->wordSafeShortText($description, $controller->truncateChars) : $description;
    $description = $th->entities($description);
    $property_address_str = $page->getAttribute('property_address_str');
    $property_size = $page->getAttribute('property_size');
    $property_sf_available = $page->getAttribute('property_sf_available');
    $property_type = $page->getAttribute('property_type');
    $property_availability = $page->getAttribute('property_availability');

        if(!empty($property_address_str)){
            if (is_object($thumbnail)){
            $img = Core::make('html/image', [$thumbnail]);
            $tag = $img->getTag();
            $tag->addClass('img-responsive');
            $img_tag = '<img style="width:100%;height:auto" alt="'.addslashes($title).'" src="'.$tag->src.'">';
            } else {
            $img_tag = '<img style="width:100%;height:auto" alt="'.addslashes($title).'" src="/download_file/415/0">';
            }

            $properties[$page->getCollectionID()] =
            '<div id="desc-view-'.$page->getCollectionID().'" class="desc-view" data-page-id="'.$page->getCollectionID().'">' .
            '<div class="container-fluid">' .
            '<div class="col-sm-4">'.$img_tag.'</div>' .
            '<div class="col-sm-8">'.
            '<h6>'.$title.'</h6>' .
            '<p><a href="'.$url.'" target="_blank">'.$property_address_str.'</a><br>' .
            '<b>Size:</b> '.$property_size.'<br>' .
            '<b>SF Available:</b> '.$property_sf_available.'<br>' .
            '<b>Type:</b> '.$property_type.'<br>' .
            '<b>Availability:</b> '.$property_availability.'<br>' .
            '</p>' .
            '</div>' .
            '</div>' .
            '</div>';
            
            ?>
        pageListMap<?php echo $c->getCollectionID(); ?>.updateFilterArrays('<?php echo addslashes($property_type); ?>', <?php echo $page->getCollectionID(); ?>);
        pageListMap<?php echo $c->getCollectionID(); ?>.updateFilterAvailArrays('<?php echo addslashes($property_availability); ?>', <?php echo $page->getCollectionID(); ?>);
        pageListMap<?php echo $c->getCollectionID(); ?>.codeAddress('<?php echo addslashes($property_address_str); ?>', <?php echo $page->getCollectionID(); ?>);
        <?php

        }
    } ?>
  });
});
</script>
<div class="map-wrapper">
    <div class="container-fluid">
        <div class="col-sm-8">
            <div class="filter-options">
                <label for="property_type_filter">Property Type:</label>
                <?php echo $filter_options; ?>
                <label for="property_type_filter">Availability:</label>
                <?php echo $filter_availability_options; ?>
            </div>
            <gmp-map center="36.8422376,-76.1840438" zoom="11" map-id="MAP_ID_<?php echo $c->getCollectionID(); ?>" id="MAP_ID_<?php echo $c->getCollectionID(); ?>"></gmp-map>
        </div>
        <div class="col-sm-4">
            <div class="desc-wrapper">
                <?php foreach($properties as $property){ 
                echo $property;
                } ?>
            </div>
        </div>
    </div>
    
</div>

<?php if ($c->isEditMode() && $controller->isBlockEmpty()): ?>
    <div class="ccm-edit-mode-disabled-item"><?=t('Empty Page List Block.')?></div>
<?php endif; ?>