function DefineCustomMarker( google ) {

  function CustomMarker( latlng, map, marker ) {
      this.latlng = latlng;
      this.marker = {};
      this.marker['values'] = marker;
      this.setMap( map );
      this.themap = map;
  }

  CustomMarker.prototype = new google.maps.OverlayView();

  CustomMarker.prototype.draw = function() {

      var scope = this;

      scope.marker['panes'] = scope.getPanes();

      if ( !scope.div ) {


          scope.div = document.createElement('div');

          scope.marker['dom'] = scope.div;

          scope.marker['dom'].classList.add('map-marker');
          scope.marker['dom'].classList.add(scope.marker['values']['className']);

          scope.marker['dom'].style.position = 'absolute';
          scope.marker['dom'].style.width = scope.marker['values']['width'] + 'px';
          scope.marker['dom'].style.height = scope.marker['values']['height'] + 'px';

          scope.marker['dom']['icon'] = document.createElement('div');
          scope.marker['dom']['icon'].className += 'map-marker-icon';
          scope.marker['dom']['icon'].style.backgroundImage = 'url(' + scope.marker['values']['icon'] + ')';

          scope.marker['dom']['link'] = document.createElement('a');
          scope.marker['dom']['link'].className += 'map-marker-link';
          scope.marker['dom']['link'].setAttribute( 'href', scope.marker['values']['link'] );


          scope.marker['dom']['head'] = document.createElement('div');
          scope.marker['dom']['head'].className += 'map-marker-head';

          scope.marker['dom']['head']['text'] = document.createTextNode( scope.marker['values']['title'] );
          scope.marker['dom']['head'].appendChild( scope.marker['dom']['head']['text'] );

          scope.marker['dom']['body'] = document.createElement('div');
          scope.marker['dom']['body'].className += 'map-marker-body';
          scope.marker['dom']['body'].innerHTML = scope.marker['values']['content'];

          scope.marker['dom']['content'] = document.createElement('div');
          scope.marker['dom']['content'].className += 'map-marker-content';

          scope.marker['dom']['content'].appendChild( scope.marker['dom']['head'] );
          scope.marker['dom']['content'].appendChild( scope.marker['dom']['body'] );

          scope.marker['dom'].appendChild( scope.marker['dom']['icon'] );
          scope.marker['dom'].appendChild( scope.marker['dom']['link'] );
          scope.marker['dom'].appendChild( scope.marker['dom']['content'] );

          if ( !scope.marker['values']['width'] ) {
              scope.marker['values']['width'] = 30;
          }
          if ( !scope.marker['values']['height'] ) {
              scope.marker['values']['height'] = 30;
          }

          scope.marker['panes'].overlayImage.appendChild( scope.marker['dom'] );

      }

      scope.marker['point'] = scope.getProjection().fromLatLngToDivPixel( scope.latlng );

      setTimeout(function() {
          scope.marker['dom'].className += ' has-loaded';
      }, 1000);

      if ( scope.marker['point'] ) {
          scope.marker['dom'].style.left = ( scope.marker['point'].x - ( scope.marker['values']['width'] / 2 ) ) + 'px';
          scope.marker['dom'].style.top = ( scope.marker['point'].y - ( scope.marker['values']['height'] / 2 ) ) + 'px';
          if ( scope.marker['values']['attach'] === 'top' ) {
              scope.marker['dom'].style.top = scope.marker['point'].y + 'px';
          }
          if ( scope.marker['values']['attach'] === 'bottom' ) {
              scope.marker['dom'].style.top = ( scope.marker['point'].y - scope.marker['values']['height'] ) + 'px';
          }

      }
  };

  CustomMarker.prototype.remove = function() {
      if ( this.div ) {
          if ( this.div.parentNode ) {
              this.div.parentNode.removeChild( this.div );
          }
          this.div = null;
      }
  };

  CustomMarker.prototype.getPosition = function() {
      return this.latlng;
  };

  return CustomMarker;
}

function generate_map( mapCenter, mapCanvas, mapZoom, mapMarkers, CustomMarker ) {
    var markers = [];
    var mapInitialCenter = new google.maps.LatLng( mapCenter.lat, mapCenter.lng );

    mapStyleConfig = [
      {elementType: 'geometry', stylers: [{color: '#242f3e'}]},
      {elementType: 'labels.text.stroke', stylers: [{color: '#242f3e'}]},
      {elementType: 'labels.text.fill', stylers: [{color: '#746855'}]},
      {
        featureType: 'administrative.locality',
        elementType: 'labels.text.fill',
        stylers: [{color: '#d59563'}]
      },
      {
        featureType: 'poi',
        elementType: 'labels.text.fill',
        stylers: [{color: '#d59563'}]
      },
      {
        featureType: 'poi.park',
        elementType: 'geometry',
        stylers: [{color: '#263c3f'}]
      },
      {
        featureType: 'poi.park',
        elementType: 'labels.text.fill',
        stylers: [{color: '#6b9a76'}]
      },
      {
        featureType: 'road',
        elementType: 'geometry',
        stylers: [{color: '#38414e'}]
      },
      {
        featureType: 'road',
        elementType: 'geometry.stroke',
        stylers: [{color: '#212a37'}]
      },
      {
        featureType: 'road',
        elementType: 'labels.text.fill',
        stylers: [{color: '#9ca5b3'}]
      },
      {
        featureType: 'road.highway',
        elementType: 'geometry',
        stylers: [{color: '#746855'}]
      },
      {
        featureType: 'road.highway',
        elementType: 'geometry.stroke',
        stylers: [{color: '#1f2835'}]
      },
      {
        featureType: 'road.highway',
        elementType: 'labels.text.fill',
        stylers: [{color: '#f3d19c'}]
      },
      {
        featureType: 'transit',
        elementType: 'geometry',
        stylers: [{color: '#2f3948'}]
      },
      {
        featureType: 'transit.station',
        elementType: 'labels.text.fill',
        stylers: [{color: '#d59563'}]
      },
      {
        featureType: 'water',
        elementType: 'geometry',
        stylers: [{color: '#17263c'}]
      },
      {
        featureType: 'water',
        elementType: 'labels.text.fill',
        stylers: [{color: '#515c6d'}]
      },
      {
        featureType: 'water',
        elementType: 'labels.text.stroke',
        stylers: [{color: '#17263c'}]
      }
    ];

    var mapStyleObj = new google.maps.StyledMapType( mapStyleConfig, { name: 'The Map' } );
    var windowWidth = Math.max( document.documentElement.clientWidth, window.innerWidth || 0 );
    var mapOptions = {
        scrollwheel: false,
        navigationControl: false,
        draggable: true,
        zoom: mapZoom || 12,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        center: mapInitialCenter,
        disableDefaultUI: true,
        minZoom: 6,
        maxZoom: 16,
        panControl: false,
        zoomControl: false,
        mapTypeControl: false,
        scaleControl: false,
        streetViewControl: false,
        overviewMapControl: false
    }
    var map = new google.maps.Map( mapCanvas, mapOptions );

    map.mapTypes.set( 'map_style', mapStyleObj );

    map.setMapTypeId( 'map_style' );

    try {
        populate_markers( map, mapMarkers );
    } catch ( err ) {
        console.log( err.message );
    }

    function populate_markers( map, data ) {
        var marker;
        for( var i = 0; i < data.length; i++ ) {
            try {
                var address = new google.maps.LatLng( data[i]['lat'], data[i]['lng'] );
                var customMarker = new CustomMarker( address, map, {
                    'id'        : data[i]['index'],
                    'icon'      : data[i]['icon'],
                    'title'     : data[i]['title'],
                    'content'   : data[i]['content'],
                    'link'      : data[i]['link'],
                    'label'     : data[i]['label'],
                    'width'     : data[i]['width'],
                    'height'    : data[i]['height'],
                    'className' : data[i]['className']
                });
                markers.push( customMarker );
            } catch ( err ) {
                console.log( err.message );
            }
        }
    }
}


function map_init( params ) {
  var canvas = document.getElementById( params.id );
  var map = {
      center : {
        lat : params.center.lat,
        lng : params.center.lng
      },
      canvas : canvas,
      zoom : params.zoom,
      markers: params.markers,
      CustomMarker: params.CustomMarker
  }
  google.maps.event.addDomListener( window, 'load', generate_map( map.center, map.canvas, map.zoom, map.markers, map.CustomMarker ) );
}
