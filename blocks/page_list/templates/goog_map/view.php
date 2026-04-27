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

  async function initMap() {
    let markers = [],
        contentString = [],
        infowindow = [],
        filterArray = [],
        filterAvailArray = [],
        selectedMarkers;

    //  Request the needed libraries.
    const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([
        google.maps.importLibrary('maps'),
        google.maps.importLibrary('marker'),
    ]);
    // Get the gmp-map element.
    const mapElement = $('#MAP_ID_<?php echo $c->getCollectionID(); ?>')[0];
    // Get the inner map.
    const innerMap = mapElement.innerMap;
    // Set map options.
    innerMap.setOptions({
        mapTypeControl: false,
    });
    // Add a marker positioned at the map center (Uluru).
    geocoder = new google.maps.Geocoder();

    const svgMarker = {
        path: "M-1.547 12l6.563-6.609-1.406-1.406-5.156 5.203-2.063-2.109-1.406 1.406zM0 0q2.906 0 4.945 2.039t2.039 4.945q0 1.453-0.727 3.328t-1.758 3.516-2.039 3.070-1.711 2.273l-0.75 0.797q-0.281-0.328-0.75-0.867t-1.688-2.156-2.133-3.141-1.664-3.445-0.75-3.375q0-2.906 2.039-4.945t4.945-2.039z",
        fillColor: "blue",
        fillOpacity: 0.6,
        strokeWeight: 0,
        rotation: 0,
        scale: 2,
        anchor: new google.maps.Point(0, 20),
    };

    // convert address to lat lng and set marker
    function codeAddress(address, pageID) {
        let coords = localStorage.getItem(address+'Coords');

        // check if values have already been stored for the address, if so use those instead of making another geocode request
        if(coords){
            coords = JSON.parse(coords);
            let latLng = {lat: coords[0].geometry.location.lat, lng: coords[0].geometry.location.lng};
            setMarker(address, pageID, latLng);
        } else {

            // first check local cache, if not found then geocode
            fetch('/api/cached-data/'+address+'Coords', {
                method: 'POST',
                body: JSON.stringify({ content: "" })
            }).then(response => {
                if (!response.ok) {
                    geocodeAddress(address, pageID);
                    throw new Error('HTTP error'); // Manual check for HTTP errors (404, 500)
                }
                return response.json(); // Parses the response body as JSON
            }).then(data => {
                coords = data.content;
                if(coords && coords.length > 0){
                    let latLng = {lat: coords[0].geometry.location.lat, lng: coords[0].geometry.location.lng};
                    setMarker(address, pageID, latLng);
                } else {
                    geocodeAddress(address, pageID);
                }
                
            }).catch(error => {
                geocodeAddress(address, pageID);
                console.error('Fetch error:', error)}
            ); 

        }
    }

    function geocodeAddress(address, pageID) {
        geocoder.geocode({ 'address': address }, function (results, status) {

            var latLng = {lat: results[0].geometry.location.lat (), lng: results[0].geometry.location.lng ()};
            
            if (status == 'OK') {

                // store geocode results in local cache for future use
                localStorage.setItem(address+'Coords', JSON.stringify(results));

                // store results to server for future use and to reduce number of geocode requests (which can be costly if there are a lot of properties)
                fetch('/api/cached-data/'+address+'Coords', {
                    method: 'POST',
                    body: JSON.stringify({ content: JSON.stringify(results) })
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error'); // Manual check for HTTP errors (404, 500)
                    }
                    return response.json(); // Parses the response body as JSON
                }).catch(error => console.error('Fetch error:', error)); 

                setMarker(address, pageID, latLng);

            } else {
                alert('Geocode was not successful for the following reason: ' + status);
            }
        });
    }

    // set map marker function
    function setMarker(address, pageID, latLng){

        var marker = new google.maps.Marker({
            position: latLng,
            map: innerMap
        });
        marker.addListener("click", () => {
            if(typeof selectedMarkers === 'object' && selectedMarkers !== null){
                selectedMarkers.setMap(null);
            }
            $('.desc-view').removeClass('highlight');
            scrollToAnchor('desc-view-' + pageID)
            $('#desc-view-' + pageID).addClass('highlight');

            var marker = new google.maps.Marker({
                position: latLng,
                map: innerMap,
                icon: svgMarker
            });

            selectedMarkers = marker;

        });

        markers[pageID] = (marker);

    }

    function updateFilterArrays(prop_type, pageID){
        // This function can be used to update filter arrays if markers are added/removed dynamically
        if(!Array.isArray(filterArray[prop_type])){
            filterArray[prop_type] = [];
        }
        filterArray[prop_type].push(pageID);
    }

    function updateFilterAvailArrays(prop_avail, pageID){
        // This function can be used to update filter arrays if markers are added/removed dynamically
        if(!Array.isArray(filterAvailArray[prop_avail])){
            filterAvailArray[prop_avail] = [];
        }
        filterAvailArray[prop_avail].push(pageID);
    }

    // Sets the map on all markers in the array.
    function setMapOnAll(map) {
        markers.forEach((marker) => {
            marker.setMap(map);
        });
        $('.desc-view').show();
    }

    // Removes the markers from the map, but keeps them in the array.
    function hideMarkers() {
        setMapOnAll(null);
        $('.desc-view').hide();
    }

    // Shows any markers currently in the array.
    function showMarkers() {
        setMapOnAll(innerMap);
        $('.desc-view').show();
    }

    $('#property_type_filter').on('change', function() {
        filterAllThings();
    });

    $('#property_availability_filter').on('change', function() {
        filterAllThings();
    });

    function filterByType(type) {
        // Get selected markers
        if(Array.isArray(filterArray[type])){
            return filterArray[type];
        }
    }

    function filterByAvailability(avail) {
        // Get selected markers
        if(Array.isArray(filterAvailArray[avail])){
            return filterAvailArray[avail];
        }
    }

    function filterAllThings(){
        let selectedType = $('#property_type_filter').val(),
            selectedAvail = $('#property_availability_filter').val(),
            typeMarkers = [],
            valueMarkers = [];

        // Hide all markers
        hideMarkers();

        // cleaer selected marker highlight
        $('.desc-view').removeClass('highlight');
        if(typeof selectedMarkers === 'object' && selectedMarkers !== null){
            selectedMarkers.setMap(null);
        }

        if(selectedType == '' && selectedAvail == ''){
            // Show all markers
            showMarkers();
            return;
        }

        // Get selected markers
        if(selectedType != '' && selectedAvail != ''){
            typeMarkers = filterByType(selectedType);
            valueMarkers = filterByAvailability(selectedAvail);
            if(!Array.isArray(typeMarkers)){
                typeMarkers = [];
            }
            if(!Array.isArray(valueMarkers)){
                valueMarkers = [];
            }
            const commonValues = typeMarkers.filter(element => {
                return valueMarkers.includes(element);
            });
            commonValues.forEach((item) => {
                markers[item].setMap(innerMap);
                $('#desc-view-' + item).show();
            });
            
        } else {
            if(selectedType != ''){
                // Get selected markers
                if(Array.isArray(filterArray[selectedType])){
                    filterArray[selectedType].forEach((item) => {
                        markers[item].setMap(innerMap);
                        $('#desc-view-' + item).show();
                    });
                }
            }
            if(selectedAvail != ''){
                // Get selected markers
                if(Array.isArray(filterAvailArray[selectedAvail])){
                    filterAvailArray[selectedAvail].forEach((item) => {
                        markers[item].setMap(innerMap);
                        $('#desc-view-' + item).show();
                    });
                }
            }
        }
    }

    function scrollToAnchor(contID) {
        const anchor = document.getElementById(contID);
        
        if (anchor) {
            anchor.scrollIntoView({
            container: 'nearest',
            behavior: 'smooth', // Optional: adds smooth scrolling animation
            block: 'start'      // Optional: aligns the top of the element with the top of the container
            });
        }
    }

    $('.desc-view').mouseenter(function(){
        const pageID = $(this).data('page-id'),
            marker = markers[pageID];

        if(typeof selectedMarkers === 'object' && selectedMarkers !== null){
            selectedMarkers.setMap(null);
        }

        $('#desc-view-' + pageID).addClass('highlight');

        var markerNew = new google.maps.Marker({
            position: marker.getPosition(),
            map: innerMap,
            icon: svgMarker
        });

        selectedMarkers = markerNew;
    }).mouseleave(function(){ 
        if(typeof selectedMarkers === 'object' && selectedMarkers !== null){
            selectedMarkers.setMap(null);
        }
        $('.desc-view').removeClass('highlight');
    });

    <?php foreach ($pages as $page){ 
        
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

    updateFilterArrays('<?php echo addslashes($property_type); ?>', <?php echo $page->getCollectionID();?>)

    updateFilterAvailArrays('<?php echo addslashes($property_availability); ?>', <?php echo $page->getCollectionID();?>);

    codeAddress('<?php echo addslashes($property_address_str); ?>', <?php echo $page->getCollectionID();?>);
    <?php }
    } ?>
}
initMap();
   
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