(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
key: "",
v: "weekly",
// Use the 'v' parameter to indicate the version to use (weekly, beta, alpha, etc.).
// Add other bootstrap parameters as needed, using camel case.
});

const delayMapMarkers = (ms) => new Promise(resolve => setTimeout(resolve, ms));

$(document).ready(function(){

});

class PageListMap {
    constructor(mapId) {
        this.mapId = mapId;
        this.markers = [];
        this.filterArray = [];
        this.filterAvailArray = [];
        this.selectedMarker = null;
        this.geocodePromises = {};
        this.geocoder = null;
        this.AdvancedMarkerElement = null;
        this.pinView = null;
        this.mapElement = null;
        this.innerMap = null;
        
        // Cache DOM elements
        this.$propertyTypeFilter = null;
        this.$propertyAvailFilter = null;
        this.$descViews = null;

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

        // Cache DOM elements
        this.$propertyTypeFilter = $('#property_type_filter');
        this.$propertyAvailFilter = $('#property_availability_filter');
        this.$descViews = $('.desc-view');

        this.attachEvents();

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
        this.clearSelectedMarker();
        this.$descViews.removeClass('highlight');
        this.scrollToAnchor('desc-view-' + pageID);
        $(`#desc-view-${pageID}`).addClass('highlight');
        this.createHighlightMarker(latLng);
        });

        this.markers[pageID] = marker;
    }

    createHighlightMarker(latLng) {
        const highlightMarker = new this.AdvancedMarkerElement({
            position: latLng,
            map: this.innerMap,
            content: this.pinView.element
        });
        this.selectedMarker = highlightMarker;
    }

    clearSelectedMarker() {
        if (this.selectedMarker) {
            this.selectedMarker.map = null;
        }
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
    }

    hideAllMarkers() {
        this.setMapOnAll(null);
        this.$descViews.hide();
    }

    showAllMarkers() {
        this.setMapOnAll(this.innerMap);
        this.$descViews.show();
    }

    attachEvents() {
        this.$propertyTypeFilter.on('change', () => this.filterAllThings());
        this.$propertyAvailFilter.on('change', () => this.filterAllThings());

        this.$descViews.on('mouseenter', (event) => {
            const pageID = $(event.currentTarget).data('page-id');
            const marker = this.markers[pageID];

            this.clearSelectedMarker();
            $(`#desc-view-${pageID}`).addClass('highlight');
            this.createHighlightMarker(marker.position);
        }).on('mouseleave', () => {
            this.clearSelectedMarker();
            this.$descViews.removeClass('highlight');
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
        const selectedType = this.$propertyTypeFilter.val();
        const selectedAvail = this.$propertyAvailFilter.val();

        this.hideAllMarkers();
        this.$descViews.removeClass('highlight');
        this.clearSelectedMarker();

        if (selectedType === '' && selectedAvail === '') {
        this.showAllMarkers();
        return;
        }

        if (selectedType !== '' && selectedAvail !== '') {
        const typeMarkers = this.filterByType(selectedType);
        const valueMarkers = this.filterByAvailability(selectedAvail);
        const commonValues = typeMarkers.filter(item => valueMarkers.includes(item));
        this.displayMarkers(commonValues);
        } else {
        const itemsToShow = selectedType !== '' 
            ? this.filterByType(selectedType) 
            : this.filterByAvailability(selectedAvail);
        this.displayMarkers(itemsToShow);
        }
    }

    displayMarkers(pageIDs) {
        pageIDs.forEach((item) => {
            this.markers[item].map = this.innerMap;
            $(`#desc-view-${item}`).show();
        });
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