let map;
let directionsService;
let directionsRenderer;
let pickupMarker;
let deliveryMarker;

function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 0, lng: 0 },
        zoom: 2
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer();
    directionsRenderer.setMap(map);

    initAutocomplete();
}

function initAutocomplete() {
    const pickupInput = document.getElementById('pickup');
    const deliveryInput = document.getElementById('delivery');

    const pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput);
    const deliveryAutocomplete = new google.maps.places.Autocomplete(deliveryInput);

    pickupAutocomplete.addListener('place_changed', () => {
        const place = pickupAutocomplete.getPlace();
        updatePickupLocation(place);
    });

    deliveryAutocomplete.addListener('place_changed', () => {
        const place = deliveryAutocomplete.getPlace();
        updateDeliveryLocation(place);
    });
}

function calculateRoute(origin, destination) {
    const request = {
        origin: origin,
        destination: destination,
        travelMode: google.maps.TravelMode.DRIVING
    };

    directionsService.route(request, (result, status) => {
        if (status === 'OK') {
            directionsRenderer.setDirections(result);
            const distance = result.routes[0].legs[0].distance.value / 1000; // Convert to km
            calculator.quoteData.distance = distance;
            document.getElementById('quote-distance').textContent = distance.toFixed(2);
        }
    });
}