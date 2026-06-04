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

class PageListMap {
    constructor(mapId) {
        this.mapId = mapId;
        this.markers = [];
        this.filterArray = [];
        this.filterAvailArray = [];
        this.selectedMarker = null;
        this.geocodePromises = {};
        this.geocoder = '';
        this.AdvancedMarkerElement = '';
        this.pinView = '';

        this.initMap();
    }

    async initMap() {
        const [{ Map }, { AdvancedMarkerElement }] = await Promise.all([
        google.maps.importLibrary('maps'),
        google.maps.importLibrary('marker'),
        google.maps.importLibrary('geocoding'),
        ]);

        this.geocoder = new google.maps.Geocoder();
        this.AdvancedMarkerElement = AdvancedMarkerElement;

        this.pinView = new google.maps.marker.PinElement({
            background: "#D1B88C", 
            glyphColor: "#000",
            borderColor: "#000",
        });

        this.mapElement = $(`#MAP_ID_${this.mapId}`)[0];
        this.innerMap = this.mapElement.innerMap;
        this.innerMap.setOptions({ mapTypeControl: false });

        this.attachEvents();

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
        this.updateFilterArrays('<?php echo addslashes($property_type); ?>', <?php echo $page->getCollectionID(); ?>);
        this.updateFilterAvailArrays('<?php echo addslashes($property_availability); ?>', <?php echo $page->getCollectionID(); ?>);
        this.codeAddress('<?php echo addslashes($property_address_str); ?>', <?php echo $page->getCollectionID(); ?>);
        <?php

        }
        } ?>
    }

    parseCachedCoords(cached) {
        if (typeof cached === 'string') {
        try {
            cached = JSON.parse(cached);
        } catch (e) {
            return null;
        }
        }
        if (Array.isArray(cached) && cached.length > 0 && cached[0].geometry && cached[0].geometry.location) {
        return { lat: cached[0].geometry.location.lat, lng: cached[0].geometry.location.lng };
        }
        return null;
    }

    codeAddress(address, pageID) {
        let coords = localStorage.getItem(address + 'Coords');

        if (coords) {
        const latLng = this.parseCachedCoords(coords);
        if (latLng) {
            this.setMarker(address, pageID, latLng);
            return;
        }
        }
        if (!this.geocodePromises[address]) {
        this.geocodePromises[address] = fetch('/api/cached-data/' + encodeURIComponent(address) + 'Coords', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content: "" })
        })
            .then(response => {
            if (!response.ok) {
                return Promise.reject(new Error('HTTP error'));
            }
            return response.json();
            })
            .then(data => {
            const cachedLatLng = this.parseCachedCoords(data.content);
            if (cachedLatLng) {
                return cachedLatLng;
            }
            return this.geocodeAddress(address);
            })
            .catch(error => {
            delete this.geocodePromises[address];
            console.error('Fetch error:', error);
            return this.geocodeAddress(address);
            });
        }

        this.geocodePromises[address]
        .then(latLng => {
            if (latLng) {
            this.setMarker(address, pageID, latLng);
            }
        })
        .catch(error => console.error('Geocode error:', error));
    }

    geocodeAddress(address) {
        return new Promise((resolve, reject) => {
        this.geocoder.geocode({ address: address }, (results, status) => {
            if (status === 'OK' && results && results[0]) {
            const latLng = {
                lat: results[0].geometry.location.lat(),
                lng: results[0].geometry.location.lng()
            };

            localStorage.setItem(address + 'Coords', JSON.stringify(results));

            fetch('/api/cached-data/' + encodeURIComponent(address) + 'Coords', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: JSON.stringify(results) })
            }).then(response => {
                if (!response.ok) {
                throw new Error('HTTP error');
                }
                return response.json();
            }).catch(error => console.error('Fetch error:', error));

            resolve(latLng);
            } else {
            console.warn('Geocode was not successful for the following reason:', status);
            reject(status);
            }
        });
        });
    }

    setMarker(address, pageID, latLng) {
        const marker = new this.AdvancedMarkerElement({
        position: latLng,
        map: this.innerMap
        });

        marker.addListener('click', () => {
        if (this.selectedMarker) {
            this.selectedMarker.map = null;
        }
        $('.desc-view').removeClass('highlight');
        this.scrollToAnchor('desc-view-' + pageID);
        $(`#desc-view-${pageID}`).addClass('highlight');

        const highlightMarker = new this.AdvancedMarkerElement({
            position: latLng,
            map: this.innerMap,
            content: this.pinView.element
        });

        this.selectedMarker = highlightMarker;
        });

        this.markers[pageID] = marker;
    }

    updateFilterArrays(prop_type, pageID) {
        if (!Array.isArray(this.filterArray[prop_type])) {
        this.filterArray[prop_type] = [];
        }
        this.filterArray[prop_type].push(pageID);
    }

    updateFilterAvailArrays(prop_avail, pageID) {
        if (!Array.isArray(this.filterAvailArray[prop_avail])) {
        this.filterAvailArray[prop_avail] = [];
        }
        this.filterAvailArray[prop_avail].push(pageID);
    }

    setMapOnAll(map) {
        this.markers.forEach((marker) => {
        marker.map = map;
        });
        $('.desc-view').show();
    }

    hideMarkers() {
        this.setMapOnAll(null);
        $('.desc-view').hide();
    }

    showMarkers() {
        this.setMapOnAll(this.innerMap);
        $('.desc-view').show();
    }

    attachEvents() {
        $('#property_type_filter').on('change', () => {
        this.filterAllThings();
        });
        $('#property_availability_filter').on('change', () => {
        this.filterAllThings();
        });

        $('.desc-view').mouseenter((event) => {
        const pageID = $(event.currentTarget).data('page-id');
        const marker = this.markers[pageID];

        if (this.selectedMarker) {
            this.selectedMarker.map = null;
        }

        $(`#desc-view-${pageID}`).addClass('highlight');

        const markerNew = new this.AdvancedMarkerElement({
            position: marker.position,
            map: this.innerMap,
            content: this.pinView.element
        });

        this.selectedMarker = markerNew;
        }).mouseleave(() => {
        if (this.selectedMarker) {
            this.selectedMarker.map = null;
        }
        $('.desc-view').removeClass('highlight');
        });
    }

    filterByType(type) {
        if (Array.isArray(this.filterArray[type])) {
        return this.filterArray[type];
        }
        return [];
    }

    filterByAvailability(avail) {
        if (Array.isArray(this.filterAvailArray[avail])) {
        return this.filterAvailArray[avail];
        }
        return [];
    }

    filterAllThings() {
        const selectedType = $('#property_type_filter').val();
        const selectedAvail = $('#property_availability_filter').val();
        let typeMarkers = [];
        let valueMarkers = [];

        this.hideMarkers();
        $('.desc-view').removeClass('highlight');
        if (this.selectedMarker) {
        this.selectedMarker.map = null;
        }

        if (selectedType === '' && selectedAvail === '') {
        this.showMarkers();
        return;
        }

        if (selectedType !== '' && selectedAvail !== '') {
        typeMarkers = this.filterByType(selectedType);
        valueMarkers = this.filterByAvailability(selectedAvail);
        const commonValues = typeMarkers.filter(item => valueMarkers.includes(item));
        commonValues.forEach((item) => {
            this.markers[item].map = this.innerMap;
            $(`#desc-view-${item}`).show();
        });
        } else {
        if (selectedType !== '') {
            this.filterByType(selectedType).forEach((item) => {
            this.markers[item].map = this.innerMap;
            $(`#desc-view-${item}`).show();
            });
        }
        if (selectedAvail !== '') {
            this.filterByAvailability(selectedAvail).forEach((item) => {
            this.markers[item].map = this.innerMap;
            $(`#desc-view-${item}`).show();
            });
        }
        }
    }

    scrollToAnchor(contID) {
        const anchor = document.getElementById(contID);
        if (anchor) {
        anchor.scrollIntoView({
            container: 'nearest',
            behavior: 'smooth',
            block: 'start'
        });
        }
    }
}

$(document).ready(function(){
  const pageListMap = new PageListMap(<?php echo $c->getCollectionID(); ?>);
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