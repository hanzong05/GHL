<?php
/**
 * Plugin Name: BlueSpot Local Guide
 * Description: Local search engine for BlueSpot Guide. WHAT + WHERE = RESULTS, ranked by relevance and distance. Businesses sync automatically from the Google My Maps on your Local Area Guides, get search tags from a synonym dictionary, and appear on crawlable pages at /explore/.
 * Version:     3.0.0
 * Author:      BlueSpot
 * Text Domain: bsg-local
 *
 * HOW IT WORKS
 *   1. SYNC (nightly + "Run sync now"): reads the business ads already on every Local Area
 *      Guide (the BSG_DATA list the guide cards are built from) and creates/updates a Local
 *      Business for each: name, category, type, address, phone, website, exact coordinates
 *      (from the Google Maps link), ad image, promo, CTA and keywords, linked to the guide.
 *      Adding an ad to a guide is all it takes. (Optionally also reads Google My Maps pins.)
 *   2. TAGS: search tags are added automatically from a synonym dictionary
 *      (e.g. "Mexican Food: mexican, taco, tacos, burrito, margarita...").
 *   3. SEARCH: "Mexican food near Cartersville, GA" -> WHAT = Mexican food, WHERE = Cartersville.
 *      Finds the centre point, searches within a per-category radius (expanding if too few
 *      results) and ranks by relevance first, then distance, then Featured/Advertiser/Favorite.
 *
 * URLS
 *   /explore/search/?q=mexican+food+near+cartersville+ga
 *   /explore/georgia/                              choose a destination
 *   /explore/georgia/things-to-do/                 choose a destination
 *   /explore/georgia/cartersville/                 every category, with counts
 *   /explore/georgia/cartersville/things-to-do/    Things to Do near Cartersville, Georgia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BSG_Local_Guide {

	const VERSION  = '3.0.0';
	const CPT      = 'bsg_listing';
	const TAX_CAT  = 'bsg_local_category';
	const TAX_DEST = 'bsg_destination';
	const TAX_TAG  = 'bsg_tag';
	const BASE     = 'explore';
	const FAV      = 'local-favorites';
	const SESSION  = 'bsgDestination';
	const OPT      = 'bsg_local_settings';
	const LOG      = 'bsg_local_sync_log';
	const CRON     = 'bsg_local_sync';

	private static $default_cats = array(
		'food-dining'   => 'Food & Dining',
		'things-to-do'  => 'Things to Do',
		'shopping'      => 'Shopping',
		'rv-services'   => 'RV Services',
		'health-safety' => 'Health & Safety',
	);

	/* guide BSG_DATA "category" values -> plugin categories */
	private static $guide_cats = array(
		'food'         => array( 'food-dining', 'Food & Dining' ),
		'things'       => array( 'things-to-do', 'Things to Do' ),
		'attractions'  => array( 'things-to-do', 'Things to Do' ),
		'shopping'     => array( 'shopping', 'Shopping' ),
		'rvandcamping' => array( 'rv-services', 'RV Services' ),
		'rv'           => array( 'rv-services', 'RV Services' ),
		'health'       => array( 'health-safety', 'Health & Safety' ),
		'local'        => array( 'local-services', 'Local Services' ),
		'marine'       => array( 'marine-services', 'Marine Services' ),
	);

	/* words that point to a category - used for map layer names and for search text */
	private static $cat_words = array(
		'food-dining'   => array( 'food', 'dining', 'restaurant', 'restaurants', 'eat', 'eats', 'places to eat', 'where to eat', 'dinner', 'lunch', 'breakfast', 'cafe', 'coffee', 'bar', 'bars', 'brewery', 'drinks' ),
		'things-to-do'  => array( 'things to do', 'thing to do', 'attraction', 'attractions', 'activities', 'activity', 'entertainment', 'recreation', 'tours', 'fun', 'explore', 'museum', 'park', 'parks', 'events' ),
		'shopping'      => array( 'shopping', 'shop', 'shops', 'store', 'stores', 'retail', 'boutique', 'boutiques', 'grocery', 'groceries', 'gifts', 'antiques', 'market' ),
		'rv-services'   => array( 'rv services', 'rv service', 'rv repair', 'camper repair', 'rv', 'propane', 'dump station', 'rv parts', 'rv supplies', 'mobile rv', 'tires', 'auto repair' ),
		'health-safety' => array( 'health', 'safety', 'hospital', 'hospitals', 'urgent care', 'walk-in clinic', 'clinic', 'pharmacy', 'pharmacies', 'medical', 'doctor', 'emergency', 'vet', 'veterinarian', 'police', 'fire', 'telehealth' ),
		'local-services'  => array( 'local services', 'towing', 'tow', 'roadside', 'roadside assistance', 'golf cart', 'golf carts', 'car wash', 'locksmith', 'laundromat' ),
		'marine-services' => array( 'marine', 'marine services', 'boat repair', 'boat service', 'boat rental', 'boat rentals', 'marina' ),
	);

	/* search tag dictionary: "Tag: keyword, keyword, ..." (editable in Settings) */
	private static $default_dictionary = "Mexican Food: mexican, taco, tacos, burrito, burritos, margarita, margaritas, fajita, fajitas, queso, enchilada, enchiladas, tex-mex, cantina, taqueria
Pizza: pizza, pizzeria, calzone
BBQ: bbq, barbecue, barbeque, smokehouse, brisket, ribs
Burgers: burger, burgers, hamburger
Seafood: seafood, fish, shrimp, oyster, oysters, crab, lobster, catfish
Steakhouse: steak, steakhouse, steaks
Southern Food: southern, soul food, fried chicken, biscuits, grits, meat and three
Italian Food: italian, pasta, spaghetti, lasagna
Asian Food: asian, chinese, japanese, sushi, thai, hibachi, vietnamese, pho, korean
Coffee: coffee, espresso, latte, cafe, coffee shop
Breakfast & Brunch: breakfast, brunch, pancakes, waffles
Ice Cream & Desserts: ice cream, dessert, desserts, bakery, donuts, cupcakes, sweets, peach ice cream
Bars & Breweries: bar, pub, brewery, breweries, craft beer, taproom, winery, wine, cocktails, happy hour
Family Friendly: family, kids, family friendly, playground
Outdoor Dining: patio, outdoor dining, outdoor seating
Golf: golf, mini golf, putt putt, driving range
Hiking & Trails: hiking, hike, trail, trails, nature walk
Fishing: fishing, fish, bait, tackle, angling
Boating & Water: boat, boating, marina, kayak, kayaking, canoe, paddle, lake, river, tubing, swimming
Museums & History: museum, history, historic, heritage, civil war, courthouse, mounds
Parks: park, state park, national park, playground, picnic
Farms & Markets: farm, farms, farmers market, orchard, peaches, u-pick
Arts & Galleries: art, gallery, galleries, artisan, pottery
Antiques: antique, antiques, vintage, thrift
Groceries: grocery, groceries, supermarket, walmart, publix, kroger, dollar general
Outdoor Gear: outdoor gear, camping gear, outfitters, sporting goods, bass pro
RV Repair: rv repair, camper repair, rv service, mobile rv, rv mechanic, trailer repair
Propane: propane, lp gas, propane refill
RV Parts & Supplies: rv parts, rv supplies, camping world, rv store
Dump Station: dump station, sewer dump, rv dump
Tires & Auto: tire, tires, auto repair, mechanic, oil change, towing
Urgent Care: urgent care, walk-in clinic, walk in clinic, clinic, minor emergency
Hospital: hospital, emergency room, er, medical center
Pharmacy: pharmacy, drugstore, cvs, walgreens, prescriptions
Veterinarian: vet, veterinarian, animal hospital, pet clinic
Laundry: laundry, laundromat, washateria
Gas Stations: gas, fuel, diesel, gas station, truck stop";

	private static $default_radius = array(
		'food-dining'     => 20,
		'things-to-do'    => 40,
		'shopping'        => 20,
		'rv-services'     => 50,
		'health-safety'   => 25,
		'local-favorites' => 30,
		'local-services'  => 25,
		'marine-services' => 40,
		'all'             => 25,
	);

	/* %s = place name */
	private static $intros = array(
		'food-dining'     => 'Discover places to eat around %s.',
		'things-to-do'    => 'Explore attractions, activities, entertainment and local experiences around %s.',
		'shopping'        => 'Shops, groceries, boutiques and gifts around %s.',
		'rv-services'     => 'RV repair, parts, propane and mobile service around %s.',
		'health-safety'   => 'Urgent care, hospitals, pharmacies and safety resources around %s.',
		'local-favorites' => 'Standout local picks our team recommends around %s.',
	);

	private static $states = array(
		'alabama' => array( 'Alabama', 'al' ), 'alaska' => array( 'Alaska', 'ak' ), 'arizona' => array( 'Arizona', 'az' ),
		'arkansas' => array( 'Arkansas', 'ar' ), 'california' => array( 'California', 'ca' ), 'colorado' => array( 'Colorado', 'co' ),
		'connecticut' => array( 'Connecticut', 'ct' ), 'delaware' => array( 'Delaware', 'de' ), 'florida' => array( 'Florida', 'fl' ),
		'georgia' => array( 'Georgia', 'ga' ), 'hawaii' => array( 'Hawaii', 'hi' ), 'idaho' => array( 'Idaho', 'id' ),
		'illinois' => array( 'Illinois', 'il' ), 'indiana' => array( 'Indiana', 'in' ), 'iowa' => array( 'Iowa', 'ia' ),
		'kansas' => array( 'Kansas', 'ks' ), 'kentucky' => array( 'Kentucky', 'ky' ), 'louisiana' => array( 'Louisiana', 'la' ),
		'maine' => array( 'Maine', 'me' ), 'maryland' => array( 'Maryland', 'md' ), 'massachusetts' => array( 'Massachusetts', 'ma' ),
		'michigan' => array( 'Michigan', 'mi' ), 'minnesota' => array( 'Minnesota', 'mn' ), 'mississippi' => array( 'Mississippi', 'ms' ),
		'missouri' => array( 'Missouri', 'mo' ), 'montana' => array( 'Montana', 'mt' ), 'nebraska' => array( 'Nebraska', 'ne' ),
		'nevada' => array( 'Nevada', 'nv' ), 'new-hampshire' => array( 'New Hampshire', 'nh' ), 'new-jersey' => array( 'New Jersey', 'nj' ),
		'new-mexico' => array( 'New Mexico', 'nm' ), 'new-york' => array( 'New York', 'ny' ), 'north-carolina' => array( 'North Carolina', 'nc' ),
		'north-dakota' => array( 'North Dakota', 'nd' ), 'ohio' => array( 'Ohio', 'oh' ), 'oklahoma' => array( 'Oklahoma', 'ok' ),
		'oregon' => array( 'Oregon', 'or' ), 'pennsylvania' => array( 'Pennsylvania', 'pa' ), 'rhode-island' => array( 'Rhode Island', 'ri' ),
		'south-carolina' => array( 'South Carolina', 'sc' ), 'south-dakota' => array( 'South Dakota', 'sd' ), 'tennessee' => array( 'Tennessee', 'tn' ),
		'texas' => array( 'Texas', 'tx' ), 'utah' => array( 'Utah', 'ut' ), 'vermont' => array( 'Vermont', 'vt' ),
		'virginia' => array( 'Virginia', 'va' ), 'washington' => array( 'Washington', 'wa' ), 'west-virginia' => array( 'West Virginia', 'wv' ),
		'wisconsin' => array( 'Wisconsin', 'wi' ), 'wyoming' => array( 'Wyoming', 'wy' ),
	);

	/* =====================================================================
	 * BOOT
	 * ===================================================================== */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_explore' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head_meta' ), 2 );
		add_action( 'wp_footer', array( __CLASS__, 'session_script' ), 50 );
		add_shortcode( 'bsg_local_listings', array( __CLASS__, 'shortcode' ) );
		add_action( self::CRON, array( __CLASS__, 'sync' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );

		foreach ( array( 'wpseo_title', 'seopress_titles_title', 'rank_math/frontend/title' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'seo_title' ), 99 );
		}
		foreach ( array( 'wpseo_canonical', 'seopress_titles_canonical', 'rank_math/frontend/canonical',
			'wpseo_metadesc', 'seopress_titles_desc', 'rank_math/frontend/description',
			'wpseo_opengraph_url', 'wpseo_opengraph_title' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'seo_blank' ), 99 );
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
			add_action( 'admin_post_bsg_local_save', array( __CLASS__, 'save_settings' ) );
			add_action( 'admin_post_bsg_local_sync_now', array( __CLASS__, 'sync_now' ) );
			add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
			add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_listing' ), 20, 2 );
			add_action( 'save_post', array( __CLASS__, 'save_page_destination' ), 10, 2 );
			add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
			add_action( self::TAX_DEST . '_add_form_fields', array( __CLASS__, 'dest_add_fields' ) );
			add_action( self::TAX_DEST . '_edit_form_fields', array( __CLASS__, 'dest_edit_fields' ) );
			add_filter( 'manage_edit-' . self::TAX_DEST . '_columns', array( __CLASS__, 'dest_columns' ) );
			add_filter( 'manage_' . self::TAX_DEST . '_custom_column', array( __CLASS__, 'dest_column_value' ), 10, 3 );
		}
		add_action( 'created_' . self::TAX_DEST, array( __CLASS__, 'save_dest_fields' ) );
		add_action( 'edited_' . self::TAX_DEST, array( __CLASS__, 'save_dest_fields' ) );
	}

	public static function activate() {
		self::register();
		self::ensure_terms();
		self::rewrites();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 300, 'daily', self::CRON );
		}
		update_option( 'bsg_local_version', self::VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'bsg_local_version' ) !== self::VERSION ) {
			self::ensure_terms();
			flush_rewrite_rules();
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + 300, 'daily', self::CRON );
			}
			update_option( 'bsg_local_version', self::VERSION );
		}
	}

	private static function ensure_terms() {
		foreach ( self::$default_cats as $slug => $name ) {
			if ( ! term_exists( $slug, self::TAX_CAT ) ) {
				wp_insert_term( $name, self::TAX_CAT, array( 'slug' => $slug ) );
			}
		}
	}

	/* =====================================================================
	 * DATA MODEL
	 * ===================================================================== */
	public static function register() {
		register_post_type( self::CPT, array(
			'labels'        => array(
				'name'          => 'Local Businesses',
				'singular_name' => 'Local Business',
				'add_new_item'  => 'Add Local Business',
				'edit_item'     => 'Edit Local Business',
				'all_items'     => 'All Local Businesses',
				'search_items'  => 'Search Local Businesses',
				'menu_name'     => 'Local Businesses',
			),
			'public'        => false,
			'show_ui'       => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-store',
			'menu_position' => 21,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
		) );

		register_taxonomy( self::TAX_CAT, self::CPT, array(
			'labels'            => array( 'name' => 'Categories', 'singular_name' => 'Category', 'menu_name' => 'Categories' ),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
		) );

		register_taxonomy( self::TAX_DEST, self::CPT, array(
			'labels'            => array( 'name' => 'Destinations', 'singular_name' => 'Destination', 'menu_name' => 'Destinations', 'add_new_item' => 'Add Destination' ),
			'description'       => 'Optional service areas. Businesses are found by distance automatically; tick a destination only to force a business into it.',
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => false,
		) );

		register_taxonomy( self::TAX_TAG, self::CPT, array(
			'labels'            => array( 'name' => 'Search Tags', 'singular_name' => 'Search Tag', 'menu_name' => 'Search Tags', 'add_new_item' => 'Add Search Tag' ),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
		) );
	}

	public static function categories() {
		$out   = array();
		$terms = get_terms( array( 'taxonomy' => self::TAX_CAT, 'hide_empty' => false, 'parent' => 0 ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[ $t->slug ] = $t->name;
			}
		}
		if ( empty( $out ) ) {
			$out = self::$default_cats;
		}
		$out[ self::FAV ] = 'Local Favorites';
		return $out;
	}

	/* =====================================================================
	 * SETTINGS + DICTIONARY
	 * ===================================================================== */
	public static function settings() {
		$s = get_option( self::OPT, array() );
		$s = is_array( $s ) ? $s : array();
		return array(
			'radius'     => array_merge( self::$default_radius, isset( $s['radius'] ) ? (array) $s['radius'] : array() ),
			'min'        => isset( $s['min'] ) ? max( 1, (int) $s['min'] ) : 6,
			'dictionary' => isset( $s['dictionary'] ) && '' !== trim( $s['dictionary'] ) ? $s['dictionary'] : self::$default_dictionary,
			'maps'       => isset( $s['maps'] ) ? $s['maps'] : '',
			'google_key' => isset( $s['google_key'] ) ? $s['google_key'] : '',
			'unpublish'  => isset( $s['unpublish'] ) ? (bool) $s['unpublish'] : true,
			'read_maps'  => ! empty( $s['read_maps'] ),
			/* guide = a campground shows only its own Local Area Guide businesses
			   destination = a campground just sets its city and shows everything nearby */
			'camp_mode'  => isset( $s['camp_mode'] ) && in_array( $s['camp_mode'], array( 'guide', 'destination' ), true ) ? $s['camp_mode'] : 'nearby',
		);
	}

	/** Parsed dictionary: array( 'Mexican Food' => array('mexican','taco',...) ). */
	public static function dictionary() {
		static $dict = null;
		if ( null !== $dict ) {
			return $dict;
		}
		$dict = array();
		foreach ( preg_split( '/\r\n|\r|\n/', self::settings()['dictionary'] ) as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $tag, $words ) = array_map( 'trim', explode( ':', $line, 2 ) );
			if ( '' === $tag ) {
				continue;
			}
			$list = array( strtolower( $tag ) );
			foreach ( explode( ',', $words ) as $w ) {
				$w = strtolower( trim( $w ) );
				if ( '' !== $w ) {
					$list[] = $w;
				}
			}
			$dict[ $tag ] = array_values( array_unique( $list ) );
		}
		return $dict;
	}

	/** Does $text contain $phrase as whole words? */
	private static function has_phrase( $text, $phrase ) {
		return (bool) preg_match( '/(^|[^a-z0-9])' . preg_quote( $phrase, '/' ) . '([^a-z0-9]|$)/', $text );
	}

	private static function norm( $text ) {
		$text = strtolower( wp_strip_all_tags( (string) $text ) );
		$text = str_replace( array( '&amp;', '&', "\xE2\x80\x99", "'" ), array( ' and ', ' and ', '', '' ), $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/** Guess a category slug from any text (map layer name, business type...). */
	private static function guess_category( $text ) {
		$text = self::norm( $text );
		$best = '';
		$len  = 0;
		foreach ( self::$cat_words as $slug => $words ) {
			foreach ( $words as $w ) {
				if ( strlen( $w ) > $len && self::has_phrase( $text, $w ) ) {
					$best = $slug;
					$len  = strlen( $w );
				}
			}
		}
		return $best;
	}

	/** Add dictionary tags that match the business text. */
	private static function auto_tag( $post_id, $text ) {
		$text = self::norm( $text );
		$add  = array();
		foreach ( self::dictionary() as $tag => $words ) {
			foreach ( $words as $w ) {
				if ( strlen( $w ) > 2 && self::has_phrase( $text, $w ) ) {
					$add[] = $tag;
					break;
				}
			}
		}
		if ( $add ) {
			wp_set_object_terms( $post_id, $add, self::TAX_TAG, true );
		}
	}

	/* =====================================================================
	 * GEO
	 * ===================================================================== */
	public static function miles( $lat1, $lng1, $lat2, $lng2 ) {
		$r    = 3958.8;
		$dlat = deg2rad( $lat2 - $lat1 );
		$dlng = deg2rad( $lng2 - $lng1 );
		$a    = sin( $dlat / 2 ) * sin( $dlat / 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) * sin( $dlng / 2 );
		return $r * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
	}

	/** Geocode an address or "City, State" -> array(lat, lng) or null. Cached for 60 days. */
	public static function geocode( $text ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return null;
		}
		$key    = 'bsg_geo_' . md5( strtolower( $text ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached['lat'] ? array( $cached['lat'], $cached['lng'] ) : null;
		}
		$lat = 0;
		$lng = 0;
		$gk  = self::settings()['google_key'];
		if ( $gk ) {
			$res = wp_remote_get( 'https://maps.googleapis.com/maps/api/geocode/json?address=' . rawurlencode( $text ) . '&components=country:US&key=' . rawurlencode( $gk ), array( 'timeout' => 10 ) );
			if ( ! is_wp_error( $res ) ) {
				$j = json_decode( wp_remote_retrieve_body( $res ), true );
				if ( ! empty( $j['results'][0]['geometry']['location'] ) ) {
					$lat = (float) $j['results'][0]['geometry']['location']['lat'];
					$lng = (float) $j['results'][0]['geometry']['location']['lng'];
				}
			}
		} else {
			/* OpenStreetMap Nominatim: free, 1 request per second, needs a real contact */
			static $last = 0;
			$wait = 1.1 - ( microtime( true ) - $last );
			if ( $wait > 0 ) {
				usleep( (int) ( $wait * 1000000 ) );
			}
			$last = microtime( true );
			$res  = wp_remote_get( 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=us&q=' . rawurlencode( $text ), array(
				'timeout' => 10,
				'headers' => array( 'User-Agent' => 'BlueSpotGuide/3.0 (' . get_option( 'admin_email' ) . ')' ),
			) );
			if ( ! is_wp_error( $res ) ) {
				$j = json_decode( wp_remote_retrieve_body( $res ), true );
				if ( ! empty( $j[0]['lat'] ) ) {
					$lat = (float) $j[0]['lat'];
					$lng = (float) $j[0]['lon'];
				}
			}
		}
		set_transient( $key, array( 'lat' => $lat, 'lng' => $lng ), $lat ? 60 * DAY_IN_SECONDS : DAY_IN_SECONDS );
		return $lat ? array( $lat, $lng ) : null;
	}

	/* =====================================================================
	 * PLACES: destinations (optional terms), states, free text
	 * ===================================================================== */
	private static function abbr( $state ) {
		return isset( self::$states[ $state ] ) ? self::$states[ $state ][1] : '';
	}

	private static function state_slug( $text ) {
		$t = sanitize_title( $text );
		if ( isset( self::$states[ $t ] ) ) {
			return $t;
		}
		$t = strtolower( trim( $text ) );
		foreach ( self::$states as $slug => $info ) {
			if ( $info[1] === $t ) {
				return $slug;
			}
		}
		return '';
	}

	private static function dest_term( $state, $city_slug ) {
		$term = get_term_by( 'slug', $city_slug . '-' . self::abbr( $state ), self::TAX_DEST );
		return ( $term && ! is_wp_error( $term ) ) ? $term : null;
	}

	/**
	 * A place to search around.
	 * array( type=>city|state, city, city_slug, state, state_name, name, lat, lng, term_id, guide )
	 */
	public static function place_for( $state, $city_slug = '' ) {
		if ( ! isset( self::$states[ $state ] ) ) {
			return null;
		}
		$sname = self::$states[ $state ][0];
		if ( ! $city_slug ) {
			return array( 'type' => 'state', 'city' => '', 'city_slug' => '', 'state' => $state, 'state_name' => $sname, 'name' => $sname, 'lat' => 0, 'lng' => 0, 'term_id' => 0, 'guide' => 0 );
		}
		$term = self::dest_term( $state, $city_slug );
		$city = $term ? $term->name : ucwords( str_replace( '-', ' ', $city_slug ) );
		$lat  = $term ? (float) get_term_meta( $term->term_id, 'bsg_lat', true ) : 0;
		$lng  = $term ? (float) get_term_meta( $term->term_id, 'bsg_lng', true ) : 0;
		if ( ! $lat ) {
			$geo = self::geocode( $city . ', ' . $sname );
			if ( $geo ) {
				list( $lat, $lng ) = $geo;
				if ( $term ) {
					update_term_meta( $term->term_id, 'bsg_lat', $lat );
					update_term_meta( $term->term_id, 'bsg_lng', $lng );
				}
			}
		}
		return array(
			'type'       => 'city',
			'city'       => $city,
			'city_slug'  => $city_slug,
			'state'      => $state,
			'state_name' => $sname,
			'name'       => $city . ', ' . $sname,
			'lat'        => $lat,
			'lng'        => $lng,
			'term_id'    => $term ? $term->term_id : 0,
			'guide'      => $term ? (int) get_term_meta( $term->term_id, 'bsg_guide', true ) : 0,
		);
	}

	/** Free text "Cartersville GA" / "cartersville, georgia" / "georgia" -> place. */
	public static function resolve_where( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', str_replace( ',', ' , ', strtolower( $text ) ) ) );
		if ( '' === $text ) {
			return null;
		}
		/* a ZIP code */
		if ( preg_match( '/^[0-9]{5}$/', trim( $text ) ) ) {
			$geo = self::geocode( trim( $text ) . ', USA' );
			return $geo ? array( 'type' => 'city', 'city' => 'ZIP ' . trim( $text ), 'city_slug' => trim( $text ), 'state' => '', 'state_name' => '',
				'name' => 'ZIP ' . trim( $text ), 'lat' => $geo[0], 'lng' => $geo[1], 'term_id' => 0, 'guide' => 0 ) : null;
		}
		$words = array_values( array_filter( explode( ' ', str_replace( ',', ' ', $text ) ) ) );
		$n     = count( $words );
		/* trailing state: "... georgia", "... north carolina", "... ga" */
		$state = '';
		$take  = 0;
		if ( $n >= 2 && isset( self::$states[ $words[ $n - 2 ] . '-' . $words[ $n - 1 ] ] ) ) {
			$state = $words[ $n - 2 ] . '-' . $words[ $n - 1 ];
			$take  = 2;
		} elseif ( $n >= 1 ) {
			$state = self::state_slug( $words[ $n - 1 ] );
			$take  = $state ? 1 : 0;
		}
		$city = trim( implode( ' ', array_slice( $words, 0, $n - $take ) ) );

		if ( $state && '' === $city ) {
			return self::place_for( $state );
		}
		if ( $state ) {
			return self::place_for( $state, sanitize_title( $city ) );
		}
		/* a campground / property name ("Forsyth Station RV Resort") */
		$mode = self::settings()['camp_mode'];
		if ( 'destination' !== $mode && strlen( $text ) > 4 ) {
			$pages = get_posts( array( 'post_type' => array( 'page', 'gd_place' ), 'post_status' => 'publish', 'posts_per_page' => 1, 'title' => ucwords( trim( str_replace( ' , ', ', ', $text ) ) ) ) );
			if ( $pages ) {
				$has_guide = get_posts( array( 'post_type' => self::CPT, 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_bsg_guide', 'meta_value' => (string) $pages[0]->ID ) ); // phpcs:ignore
				if ( 'guide' === $mode && $has_guide ) {
					return self::guide_place( $pages[0] );
				}
				if ( 'nearby' === $mode && $has_guide ) {
					$cp = self::campground_place( $pages[0] );
					if ( $cp && $cp['lat'] ) {
						return $cp;
					}
				}
			}
		}
		/* no state given: look for a destination with that city name */
		$terms = get_terms( array( 'taxonomy' => self::TAX_DEST, 'hide_empty' => false, 'name' => ucwords( $text ) ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			$st = get_term_meta( $terms[0]->term_id, 'bsg_state', true );
			if ( $st ) {
				return self::place_for( $st, sanitize_title( $terms[0]->name ) );
			}
		}
		/* last resort: geocode the text */
		$geo = self::geocode( $text );
		if ( ! $geo ) {
			return null;
		}
		return array( 'type' => 'city', 'city' => ucwords( $text ), 'city_slug' => sanitize_title( $text ), 'state' => '', 'state_name' => '',
			'name' => ucwords( $text ), 'lat' => $geo[0], 'lng' => $geo[1], 'term_id' => 0, 'guide' => 0 );
	}

	/* =====================================================================
	 * SEARCH ENGINE: relevance + distance
	 * ===================================================================== */
	/** Split "mexican food near cartersville ga" into what / where / category. */
	public static function parse_query( $q ) {
		$q     = self::norm( $q );
		$what  = $q;
		$where = '';
		if ( preg_match( '/^(.*?)\s+(?:near|in|around|by|close to|outside)\s+(.+)$/', $q, $m ) ) {
			$what  = trim( $m[1] );
			$where = trim( $m[2] );
		} elseif ( preg_match( '/^(?:near|in|around)\s+(.+)$/', $q, $m ) ) {
			$what  = '';
			$where = trim( $m[1] );
		}
		if ( preg_match( '/local favou?rites?/', $what ) ) {
			return array( 'what' => trim( preg_replace( '/local favou?rites?/', '', $what ) ), 'where' => $where, 'cat' => self::FAV );
		}
		$cat = self::guess_category( $what );
		return array( 'what' => $what, 'where' => $where, 'cat' => $cat );
	}

	/** Expand search words with dictionary synonyms. Returns weighted phrases. */
	private static function expand( $what ) {
		$what   = self::norm( $what );
		$stop   = array( 'the', 'a', 'an', 'and', 'of', 'for', 'to', 'best', 'good', 'great', 'places', 'place', 'some', 'food', 'things', 'do', 'near', 'me' );
		$terms  = array();
		$tags   = array();
		foreach ( self::dictionary() as $tag => $words ) {
			foreach ( $words as $w ) {
				if ( self::has_phrase( $what, $w ) ) {
					$tags[ $tag ] = true;
					foreach ( $words as $syn ) {
						$terms[ $syn ] = 2;
					}
					break;
				}
			}
		}
		/* words that only name a category ("restaurants", "attractions") are handled by the
		   category filter, so they don't turn the search into a keyword search */
		$cat_only = array();
		foreach ( self::$cat_words as $words ) {
			foreach ( $words as $cw ) {
				$cat_only[ $cw ] = true;
			}
		}
		foreach ( explode( ' ', $what ) as $w ) {
			if ( strlen( $w ) > 2 && ! in_array( $w, $stop, true ) && ! isset( $terms[ $w ] ) && ! isset( $cat_only[ $w ] ) ) {
				$terms[ $w ] = 3;
			}
		}
		return array( 'terms' => $terms, 'tags' => array_keys( $tags ) );
	}

	/**
	 * The engine.
	 * $o = array( lat, lng, cat, what, radius (0 = per category), term_id (service area), limit )
	 * Returns array( items => array( array(post, miles, score) ), radius => used miles )
	 */
	public static function search( $o ) {
		$o        = array_merge( array( 'lat' => 0, 'lng' => 0, 'cat' => '', 'what' => '', 'radius' => 0, 'term_id' => 0, 'guide' => 0, 'limit' => 60 ), $o );
		$set      = self::settings();
		$rkey     = $o['cat'] ? $o['cat'] : 'all';
		$radius   = $o['radius'] ? $o['radius'] : ( isset( $set['radius'][ $rkey ] ) ? (float) $set['radius'][ $rkey ] : 25 );
		$max      = $radius * 2;
		$exp      = $o['what'] ? self::expand( $o['what'] ) : array( 'terms' => array(), 'tags' => array() );
		$has_what = ! empty( $exp['terms'] );
		$lat      = (float) $o['lat'];
		$lng      = (float) $o['lng'];

		/* candidates: inside the largest box we might need + anything tied to the service area */
		$candidates = array();
		if ( $o['guide'] ) {
			/* campground mode: only the businesses in this campground's own Local Area Guide */
			$lat = 0;
			foreach ( get_posts( array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1000,
				'meta_query'     => array( array( 'key' => '_bsg_guide', 'value' => (string) (int) $o['guide'] ) ),
			) ) as $p ) {
				$candidates[ $p->ID ] = $p;
			}
		} elseif ( ! $lat && ! $o['term_id'] && $has_what ) {
			/* no place: keyword search across every business ad */
			foreach ( get_posts( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => 2000 ) ) as $p ) {
				$candidates[ $p->ID ] = $p;
			}
		} elseif ( $lat ) {
			$dlat  = $max / 69.0;
			$dlng  = $max / max( 1, 69.0 * cos( deg2rad( $lat ) ) );
			$args  = array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1500,
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => '_bsg_lat', 'value' => array( $lat - $dlat, $lat + $dlat ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(10,6)' ),
					array( 'key' => '_bsg_lng', 'value' => array( $lng - $dlng, $lng + $dlng ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(10,6)' ),
				),
			);
			foreach ( get_posts( $args ) as $p ) {
				$candidates[ $p->ID ] = $p;
			}
		}
		if ( $o['term_id'] ) {
			$tied = get_posts( array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'tax_query'      => array( array( 'taxonomy' => self::TAX_DEST, 'field' => 'term_id', 'terms' => (int) $o['term_id'] ) ),
			) );
			foreach ( $tied as $p ) {
				$candidates[ $p->ID ] = $p;
			}
		}

		$rows = array();
		foreach ( $candidates as $p ) {
			$id = $p->ID;
			/* category / Local Favorite filter */
			if ( self::FAV === $o['cat'] ) {
				if ( ! get_post_meta( $id, '_bsg_favorite', true ) ) {
					continue;
				}
			} elseif ( $o['cat'] && ! $has_what && ! has_term( $o['cat'], self::TAX_CAT, $id ) ) {
				continue;
			}

			/* distance (service-area businesses count as in-range) */
			$plat  = (float) get_post_meta( $id, '_bsg_lat', true );
			$plng  = (float) get_post_meta( $id, '_bsg_lng', true );
			$miles = ( $lat && $plat ) ? self::miles( $lat, $lng, $plat, $plng ) : 0;
			$tied  = $o['guide'] || ( $o['term_id'] && has_term( (int) $o['term_id'], self::TAX_DEST, $id ) );

			/* relevance */
			$rel = 0;
			if ( $has_what ) {
				$tags  = wp_get_post_terms( $id, self::TAX_TAG, array( 'fields' => 'names' ) );
				$cats  = wp_get_post_terms( $id, self::TAX_CAT, array( 'fields' => 'names' ) );
				$title = self::norm( $p->post_title );
				$type  = self::norm( get_post_meta( $id, '_bsg_subtype', true ) );
				$tagt  = self::norm( implode( ' | ', is_wp_error( $tags ) ? array() : $tags ) );
				$catt  = self::norm( implode( ' | ', is_wp_error( $cats ) ? array() : $cats ) );
				$desc  = self::norm( $p->post_excerpt . ' ' . $p->post_content );
				$kwt   = self::norm( get_post_meta( $id, '_bsg_keywords', true ) );
				foreach ( $exp['terms'] as $t => $w ) {
					if ( self::has_phrase( $kwt, $t ) ) {
						$rel += 4 * $w;
					}
					if ( self::has_phrase( $type, $t ) ) {
						$rel += 5 * $w;
					}
					if ( self::has_phrase( $tagt, $t ) ) {
						$rel += 4 * $w;
					}
					if ( self::has_phrase( $title, $t ) ) {
						$rel += 4 * $w;
					}
					if ( self::has_phrase( $catt, $t ) ) {
						$rel += 2 * $w;
					}
					if ( self::has_phrase( $desc, $t ) ) {
						$rel += 1 * $w;
					}
				}
				if ( ! is_wp_error( $tags ) ) {
					foreach ( $exp['tags'] as $tg ) {
						if ( in_array( $tg, $tags, true ) ) {
							$rel += 12;
						}
					}
				}
				if ( $rel <= 0 ) {
					continue; /* nothing specific matched: a nearby but unrelated business is left out */
				}
				if ( $o['cat'] && self::FAV !== $o['cat'] && has_term( $o['cat'], self::TAX_CAT, $id ) ) {
					$rel += 6;
				}
			} else {
				$rel = 10;
			}

			$boost = ( get_post_meta( $id, '_bsg_featured', true ) ? 3 : 0 )
				+ ( get_post_meta( $id, '_bsg_advertiser', true ) ? 2 : 0 )
				+ ( get_post_meta( $id, '_bsg_favorite', true ) ? 1.5 : 0 );

			$rows[] = array(
				'post'  => $p,
				'miles' => $miles,
				'tied'  => $tied,
				'score' => $rel * 10 - ( $tied ? 0 : $miles * 1.2 ) + $boost * 4,
			);
		}

		/* start with the category radius; widen it when there are too few results */
		$used = $radius;
		$pick = function ( $r ) use ( $rows, $lat ) {
			return array_values( array_filter( $rows, function ( $row ) use ( $r, $lat ) {
				return $row['tied'] || ! $lat || $row['miles'] <= $r;
			} ) );
		};
		$items = $pick( $used );
		while ( count( $items ) < $set['min'] && $used < $max ) {
			$used  = min( $max, $used * 1.5 );
			$items = $pick( $used );
		}
		usort( $items, function ( $a, $b ) {
			if ( $a['score'] === $b['score'] ) {
				return $a['miles'] <=> $b['miles'];
			}
			return ( $a['score'] < $b['score'] ) ? 1 : -1;
		} );
		if ( $o['limit'] > 0 ) {
			$items = array_slice( $items, 0, (int) $o['limit'] );
		}
		return array( 'items' => $items, 'radius' => round( $used ) );
	}

	/* =====================================================================
	 * URLS + REQUEST
	 * ===================================================================== */
	public static function rewrites() {
		add_rewrite_rule( '^' . self::BASE . '/search/?$', 'index.php?bsg_search=1', 'top' );
		add_rewrite_rule( '^' . self::BASE . '/guide/([^/]+)/([^/]+)/?$', 'index.php?bsg_guide=$matches[1]&bsg_b=$matches[2]', 'top' );
		add_rewrite_rule( '^' . self::BASE . '/guide/([^/]+)/?$', 'index.php?bsg_guide=$matches[1]', 'top' );
		add_rewrite_rule( '^' . self::BASE . '/([^/]+)/([^/]+)/([^/]+)/?$', 'index.php?bsg_state=$matches[1]&bsg_a=$matches[2]&bsg_b=$matches[3]', 'top' );
		add_rewrite_rule( '^' . self::BASE . '/([^/]+)/([^/]+)/?$', 'index.php?bsg_state=$matches[1]&bsg_a=$matches[2]', 'top' );
		add_rewrite_rule( '^' . self::BASE . '/([^/]+)/?$', 'index.php?bsg_state=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		return array_merge( $vars, array( 'bsg_state', 'bsg_a', 'bsg_b', 'bsg_search', 'bsg_guide' ) );
	}

	public static function url( $state, $city_slug = '', $cat = '' ) {
		$path = '/' . self::BASE . '/' . $state . '/';
		if ( $city_slug ) {
			$path .= $city_slug . '/';
		}
		if ( $cat ) {
			$path .= $cat . '/';
		}
		return home_url( $path );
	}

	public static function guide_url( $slug, $cat = '' ) {
		return home_url( '/' . self::BASE . '/guide/' . $slug . '/' . ( $cat ? $cat . '/' : '' ) );
	}

	/** A campground / property page as a place whose results come from its own Local Area Guide. */
	public static function guide_place( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}
		return array( 'type' => 'guide', 'page_id' => $post->ID, 'slug' => $post->post_name,
			'name' => wp_strip_all_tags( get_the_title( $post ) ), 'city' => wp_strip_all_tags( get_the_title( $post ) ),
			'state' => '', 'state_name' => '', 'city_slug' => '', 'lat' => 0, 'lng' => 0, 'term_id' => 0, 'guide' => $post->ID );
	}

	/**
	 * Where a campground is, for "near Stone Mountain Park" searches. Tried in order and
	 * remembered on the page: address in its "Get Directions" link -> average position of its
	 * own guide ads -> map lookup of its name.
	 */
	public static function campground_place( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}
		$lat = (float) get_post_meta( $post->ID, '_bsg_lat', true );
		$lng = (float) get_post_meta( $post->ID, '_bsg_lng', true );
		if ( ! $lat ) {
			global $wpdb;
			$texts = array( $post->post_content );
			foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key NOT LIKE %s AND meta_value LIKE %s", $post->ID, '%draft%', '%maps.google.com/?q=%' ) ) as $t ) { // phpcs:ignore
				$texts[] = $t;
			}
			foreach ( $texts as $t ) {
				if ( preg_match( '/maps\.google\.com\/\?q=([^"\'&<\s]+)/', $t, $m ) ) {
					$geo = self::geocode( str_replace( '+', ' ', rawurldecode( html_entity_decode( $m[1] ) ) ) );
					if ( $geo ) {
						list( $lat, $lng ) = $geo;
						break;
					}
				}
			}
		}
		if ( ! $lat ) {
			$ids = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'meta_key' => '_bsg_guide', 'meta_value' => (string) $post->ID ) ); // phpcs:ignore
			$sum = array( 0, 0, 0 );
			foreach ( $ids as $bid ) {
				$bl = (float) get_post_meta( $bid, '_bsg_lat', true );
				if ( $bl ) {
					$sum[0] += $bl;
					$sum[1] += (float) get_post_meta( $bid, '_bsg_lng', true );
					$sum[2]++;
				}
			}
			if ( $sum[2] ) {
				$lat = $sum[0] / $sum[2];
				$lng = $sum[1] / $sum[2];
			}
		}
		if ( ! $lat ) {
			$geo = self::geocode( wp_strip_all_tags( get_the_title( $post ) ) );
			if ( $geo ) {
				list( $lat, $lng ) = $geo;
			}
		}
		if ( $lat ) {
			update_post_meta( $post->ID, '_bsg_lat', $lat );
			update_post_meta( $post->ID, '_bsg_lng', $lng );
		}
		$name = wp_strip_all_tags( get_the_title( $post ) );
		return array( 'type' => 'campground', 'page_id' => $post->ID, 'slug' => $post->post_name, 'name' => $name, 'city' => $name,
			'state' => '', 'state_name' => '', 'city_slug' => '', 'lat' => $lat, 'lng' => $lng, 'term_id' => 0, 'guide' => 0 );
	}

	private static function page_by_slug( $slug ) {
		$posts = get_posts( array( 'name' => sanitize_title( $slug ), 'post_type' => array( 'page', 'gd_place', 'post' ), 'post_status' => 'publish', 'posts_per_page' => 1 ) );
		return $posts ? $posts[0] : null;
	}

	public static function search_url( $q ) {
		return add_query_arg( 'q', rawurlencode( $q ), home_url( '/' . self::BASE . '/search/' ) );
	}

	private static function request() {
		static $req = false;
		if ( false !== $req ) {
			return $req;
		}
		$req  = null;
		$cats = self::categories();

		if ( get_query_var( 'bsg_guide' ) ) {
			$page = self::page_by_slug( (string) get_query_var( 'bsg_guide' ) );
			$cat  = sanitize_title( (string) get_query_var( 'bsg_b' ) );
			if ( ! $page || ( $cat && ! isset( $cats[ $cat ] ) ) ) {
				$req = array( 'invalid' => true );
				return $req;
			}
			$req = array( 'mode' => 'guide', 'place' => self::guide_place( $page ), 'cat' => $cat, 'cats' => $cats );
			return $req;
		}

		if ( get_query_var( 'bsg_search' ) ) {
			$q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore
			$req = array( 'mode' => 'search', 'q' => $q, 'cats' => $cats );
			return $req;
		}

		$state = sanitize_title( (string) get_query_var( 'bsg_state' ) );
		if ( ! $state ) {
			return $req;
		}
		if ( ! isset( self::$states[ $state ] ) ) {
			$req = array( 'invalid' => true );
			return $req;
		}
		$a   = sanitize_title( (string) get_query_var( 'bsg_a' ) );
		$b   = sanitize_title( (string) get_query_var( 'bsg_b' ) );
		$cat = '';
		$city = '';
		if ( $a && isset( $cats[ $a ] ) && ! $b ) {
			$cat = $a;
		} elseif ( $a ) {
			$city = $a;
			$cat  = $b;
		}
		if ( $cat && ! isset( $cats[ $cat ] ) ) {
			$req = array( 'invalid' => true );
			return $req;
		}
		$req = array( 'mode' => 'place', 'place' => self::place_for( $state, $city ), 'cat' => $cat, 'cats' => $cats );
		return $req;
	}

	private static function heading( $req ) {
		if ( 'search' === $req['mode'] ) {
			return $req['q'] ? ucfirst( $req['q'] ) : 'Search BlueSpot Guide';
		}
		$p     = $req['place'];
		$label = $req['cat'] ? $req['cats'][ $req['cat'] ] : '';
		if ( 'guide' === $p['type'] ) {
			return ( $label ? $label : 'Local Recommendations' ) . ' Near ' . $p['name'];
		}
		if ( 'state' === $p['type'] ) {
			return $label ? $label . ' in ' . $p['name'] : 'Explore ' . $p['name'];
		}
		return ( $label ? $label : 'Local Recommendations' ) . ' near ' . $p['name'];
	}

	private static function current_url( $req ) {
		if ( 'search' === $req['mode'] ) {
			return self::search_url( $req['q'] );
		}
		if ( 'guide' === $req['mode'] ) {
			return self::guide_url( $req['place']['slug'], $req['cat'] );
		}
		return self::url( $req['place']['state'], $req['place']['city_slug'], $req['cat'] );
	}

	private static function is_explore() {
		$req = self::request();
		return $req && empty( $req['invalid'] );
	}

	public static function document_title( $title ) {
		return self::is_explore() ? self::heading( self::request() ) . ' | ' . get_bloginfo( 'name' ) : $title;
	}

	public static function seo_title( $title ) {
		return self::is_explore() ? self::document_title( $title ) : $title;
	}

	public static function seo_blank( $value ) {
		return self::is_explore() ? '' : $value;
	}

	public static function head_meta() {
		if ( ! self::is_explore() ) {
			return;
		}
		$req = self::request();
		$h   = self::heading( $req );
		echo '<link rel="canonical" href="' . esc_url( self::current_url( $req ) ) . "\" />\n";
		echo '<meta name="description" content="' . esc_attr( $h . ' - local recommendations from BlueSpot Guide.' ) . "\" />\n";
		echo '<meta property="og:title" content="' . esc_attr( $h ) . "\" />\n";
		if ( 'search' === $req['mode'] ) {
			echo '<meta name="robots" content="noindex,follow" />' . "\n";
		}
	}

	/* =====================================================================
	 * RESULTS PAGES
	 * ===================================================================== */
	public static function render_explore() {
		$req = self::request();
		if ( ! $req ) {
			return;
		}
		global $wp_query;
		if ( ! empty( $req['invalid'] ) ) {
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		/* campground results live on the campground's own Local Area Guide page:
		   /explore/guide/stone-mountain-park/things-to-do/ -> /stone-mountain-park/#bsg-things */
		if ( 'guide' === $req['mode'] ) {
			$anchors = array(
				'food-dining'     => 'bsg-food',
				'things-to-do'    => 'bsg-things',
				'shopping'        => 'bsg-shopping',
				'rv-services'     => 'bsg-rvc',
				'health-safety'   => 'bsg-health',
				'local-services'  => 'bsg-local',
				'marine-services' => 'bsg-marine',
				'local-favorites' => 'bsg-attractions',
			);
			$hash = $req['cat'] && isset( $anchors[ $req['cat'] ] ) ? $anchors[ $req['cat'] ] : 'bsgWrap';
			wp_safe_redirect( get_permalink( $req['place']['page_id'] ) . '#' . $hash, 301 );
			exit;
		}
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		status_header( 200 );
		get_header();
		echo self::styles(); // phpcs:ignore
		echo '<main id="bsg-explore-page"><div class="bsgx-wrap">';
		if ( 'search' === $req['mode'] ) {
			echo self::search_page( $req ); // phpcs:ignore
		} elseif ( 'guide' === $req['mode'] ) {
			echo self::guide_page( $req ); // phpcs:ignore
		} else {
			echo self::place_page( $req ); // phpcs:ignore
		}
		echo '</div></main>';
		get_footer();
		exit;
	}

	private static function cat_tabs( $place, $cats, $current ) {
		$out = '<nav class="bsgx-cats" aria-label="Categories">';
		foreach ( $cats as $slug => $label ) {
			$on   = $slug === $current;
			$out .= '<a class="' . ( $on ? 'on' : '' ) . '"' . ( $on ? ' aria-current="page"' : '' ) . ' href="' . esc_url( self::url( $place['state'], $place['city_slug'], $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return $out . '</nav>';
	}

	private static function place_page( $req ) {
		$p    = $req['place'];
		$cat  = $req['cat'];
		$cats = $req['cats'];
		$out  = '<nav class="bsgx-crumbs" aria-label="Breadcrumb"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a> <span>&rsaquo;</span> <a href="' . esc_url( self::url( $p['state'] ) ) . '">' . esc_html( $p['state_name'] ) . '</a>';
		if ( 'city' === $p['type'] ) {
			$out .= ' <span>&rsaquo;</span> <a href="' . esc_url( self::url( $p['state'], $p['city_slug'] ) ) . '">' . esc_html( $p['city'] ) . '</a>';
		}
		if ( $cat ) {
			$out .= ' <span>&rsaquo;</span> <span aria-current="page">' . esc_html( $cats[ $cat ] ) . '</span>';
		}
		$out .= '</nav>';

		$intro = 'state' === $p['type'] ? 'Choose a destination to explore local recommendations.'
			: ( $cat && isset( self::$intros[ $cat ] ) ? sprintf( self::$intros[ $cat ], $p['city'] ) : 'Everything BlueSpot Guide recommends around ' . $p['city'] . '.' );
		$out .= '<header class="bsgx-head"><h1>' . esc_html( self::heading( $req ) ) . '</h1><p>' . esc_html( $intro ) . '</p></header>';
		$out .= self::cat_tabs( $p, $cats, $cat );

		if ( 'state' === $p['type'] ) {
			return $out . self::state_list( $p, $cat );
		}
		if ( ! $p['lat'] && ! $p['term_id'] ) {
			return $out . self::empty_box( $p, $cat, $cats, 'We couldn&rsquo;t find ' . esc_html( $p['name'] ) . ' on the map.', 'Try a nearby city or choose another destination.' );
		}
		if ( ! $cat ) {
			$out .= '<div class="bsgx-dests">';
			foreach ( $cats as $slug => $label ) {
				$n    = count( self::search( array( 'lat' => $p['lat'], 'lng' => $p['lng'], 'cat' => $slug, 'term_id' => $p['term_id'], 'limit' => 0 ) )['items'] );
				$out .= '<a class="bsgx-dest" href="' . esc_url( self::url( $p['state'], $p['city_slug'], $slug ) ) . '"><strong>' . esc_html( $label ) . '</strong><span>' . ( $n ? esc_html( $n . ' ' . ( 1 === $n ? 'place' : 'places' ) ) : 'Coming soon' ) . '</span></a>';
			}
			return $out . '</div>' . self::guide_link( $p );
		}
		$res = self::search( array( 'lat' => $p['lat'], 'lng' => $p['lng'], 'cat' => $cat, 'term_id' => $p['term_id'] ) );
		if ( ! $res['items'] ) {
			return $out . self::empty_box( $p, $cat, $cats, 'We&rsquo;re still exploring this area.', 'We don&rsquo;t have ' . esc_html( $cats[ $cat ] ) . ' recommendations here yet. Explore another category or check back soon.' );
		}
		$out .= '<p class="bsgx-note">Within ' . (int) $res['radius'] . ' miles of ' . esc_html( $p['city'] ) . ', closest and most relevant first.</p>';
		return $out . self::grid( $res['items'] ) . self::guide_link( $p );
	}

	/** A campground's own Local Area Guide businesses (the businesses synced from its map). */
	private static function guide_page( $req ) {
		$p    = $req['place'];
		$cat  = $req['cat'];
		$cats = $req['cats'];
		$out  = '<nav class="bsgx-crumbs" aria-label="Breadcrumb"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a> <span>&rsaquo;</span> <a href="' . esc_url( get_permalink( $p['page_id'] ) ) . '">' . esc_html( $p['name'] ) . '</a>'
			. ( $cat ? ' <span>&rsaquo;</span> <span aria-current="page">' . esc_html( $cats[ $cat ] ) . '</span>' : '' ) . '</nav>';
		$intro = $cat && isset( self::$intros[ $cat ] ) ? sprintf( self::$intros[ $cat ], $p['name'] ) : 'Everything in the ' . $p['name'] . ' Local Area Guide.';
		$out  .= '<header class="bsgx-head"><h1>' . esc_html( self::heading( $req ) ) . '</h1><p>' . esc_html( $intro ) . '</p></header>';
		$out  .= '<nav class="bsgx-cats" aria-label="Categories">';
		foreach ( $cats as $slug => $label ) {
			$on   = $slug === $cat;
			$out .= '<a class="' . ( $on ? 'on' : '' ) . '"' . ( $on ? ' aria-current="page"' : '' ) . ' href="' . esc_url( self::guide_url( $p['slug'], $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		$out .= '</nav>';

		$res = self::search( array( 'guide' => $p['page_id'], 'cat' => $cat, 'limit' => 0 ) );
		if ( ! $res['items'] ) {
			$out .= '<div class="bsgx-empty"><h2>We&rsquo;re still exploring this area.</h2><p>This Local Area Guide doesn&rsquo;t have ' . esc_html( $cat ? $cats[ $cat ] : 'any' ) . ' recommendations yet. Explore another category or check back soon.</p><div class="bsgx-empty-links">';
			foreach ( $cats as $slug => $label ) {
				if ( $slug !== $cat ) {
					$out .= '<a href="' . esc_url( self::guide_url( $p['slug'], $slug ) ) . '">' . esc_html( $label ) . '</a>';
				}
			}
			return $out . '</div></div>' . '<p class="bsgx-back"><a href="' . esc_url( get_permalink( $p['page_id'] ) ) . '">View the ' . esc_html( $p['name'] ) . ' guide &rarr;</a></p>';
		}
		return $out . self::grid( $res['items'] ) . '<p class="bsgx-back"><a href="' . esc_url( get_permalink( $p['page_id'] ) ) . '">View the ' . esc_html( $p['name'] ) . ' guide &rarr;</a></p>';
	}

	private static function search_page( $req ) {
		$q    = $req['q'];
		$cats = $req['cats'];
		$out  = '<nav class="bsgx-crumbs"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a> <span>&rsaquo;</span> <span>Search</span></nav>';
		$out .= '<form class="bsgx-search" method="get" action="' . esc_url( home_url( '/' . self::BASE . '/search/' ) ) . '" role="search">'
			. '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="e.g. Mexican food near Cartersville, GA" aria-label="Search">'
			. '<button type="submit">Search</button></form>';

		$parsed = self::parse_query( $q );
		$place  = $parsed['where'] ? self::resolve_where( $parsed['where'] ) : null;
		$out   .= '<header class="bsgx-head"><h1>' . esc_html( self::heading( $req ) ) . '</h1>';

		if ( ! $q ) {
			return $out . '<p>Search for food, attractions, services or a destination.</p></header>';
		}
		if ( ! $place ) {
			$res = $parsed['what'] ? self::search( array( 'what' => $parsed['what'], 'cat' => $parsed['cat'] ) ) : array( 'items' => array() );
			if ( ! $res['items'] ) {
				return $out . '<p>No matches yet. Try another word, or add where you&rsquo;re headed, e.g. <em>' . esc_html( ( $parsed['what'] ? $parsed['what'] : 'tacos' ) . ' near Cartersville, GA' ) . '</em>.</p></header>';
			}
			return $out . '<p>' . esc_html( count( $res['items'] ) . ' match' . ( 1 === count( $res['items'] ) ? '' : 'es' ) . ' across BlueSpot Guide. Add a place (e.g. "near Macon, GA") to see the closest first.' ) . '</p></header>' . self::grid( $res['items'] );
		}
		if ( 'state' === $place['type'] ) {
			$out .= '<p>Choose a destination in ' . esc_html( $place['name'] ) . ' to see local results.</p></header>';
			return $out . self::state_list( $place, $parsed['cat'] );
		}
		$res  = self::search( array( 'lat' => $place['lat'], 'lng' => $place['lng'], 'cat' => $parsed['cat'], 'what' => $parsed['what'], 'term_id' => $place['term_id'], 'guide' => 'guide' === $place['type'] ? $place['page_id'] : 0 ) );
		$out .= '<p>' . ( $res['items']
			? esc_html( count( $res['items'] ) . ' result' . ( 1 === count( $res['items'] ) ? '' : 's' ) . ( 'guide' === $place['type'] ? ' in the ' . $place['name'] . ' Local Area Guide' : ' within ' . (int) $res['radius'] . ' miles of ' . $place['name'] ) . ', most relevant first.' )
			: 'We&rsquo;re still exploring this area.' ) . '</p></header>';
		if ( $place['state'] && 'guide' !== $place['type'] ) {
			$out .= self::cat_tabs( $place, $cats, '' );
		}
		if ( ! $res['items'] ) {
			return $out . self::empty_box( $place, '', $cats, 'No matches yet.', 'Try a broader search like <em>restaurants</em> or <em>things to do</em>, or check back soon.' );
		}
		return $out . self::grid( $res['items'] );
	}

	private static function guide_link( $p ) {
		return $p['guide'] ? '<p class="bsgx-back"><a href="' . esc_url( get_permalink( $p['guide'] ) ) . '">Read the full ' . esc_html( $p['city'] ) . ' guide &rarr;</a></p>' : '';
	}

	/** State: choose a destination. Destinations = Destination terms in the state. */
	private static function state_list( $place, $cat ) {
		$terms = get_terms( array( 'taxonomy' => self::TAX_DEST, 'hide_empty' => false, 'orderby' => 'name', 'meta_key' => 'bsg_state', 'meta_value' => $place['state'] ) ); // phpcs:ignore
		if ( is_wp_error( $terms ) || ! $terms ) {
			return self::empty_box( $place, $cat, self::categories(), 'We&rsquo;re still exploring ' . esc_html( $place['name'] ) . '.', 'Destination guides for this state are coming soon.' );
		}
		$out = '<div class="bsgx-dests">';
		foreach ( $terms as $t ) {
			$cs   = sanitize_title( $t->name );
			$out .= '<a class="bsgx-dest" href="' . esc_url( self::url( $place['state'], $cs, $cat ) ) . '"><strong>' . esc_html( $t->name ) . '</strong><span>Explore &rarr;</span></a>';
		}
		return $out . '</div>';
	}

	private static function empty_box( $p, $cat, $cats, $title, $text ) {
		$out = '<div class="bsgx-empty"><h2>' . $title . '</h2><p>' . $text . '</p>';
		if ( ! empty( $p['state'] ) && ! empty( $p['city_slug'] ) ) {
			$out .= '<div class="bsgx-empty-links">';
			foreach ( $cats as $slug => $label ) {
				if ( $slug !== $cat ) {
					$out .= '<a href="' . esc_url( self::url( $p['state'], $p['city_slug'], $slug ) ) . '">' . esc_html( $label ) . '</a>';
				}
			}
			$out .= '</div><p><a class="bsgx-all" href="' . esc_url( self::url( $p['state'], $p['city_slug'] ) ) . '">Explore All Local Recommendations &rarr;</a></p>';
		}
		return $out . '</div>';
	}

	private static function grid( $items ) {
		$out = '<div class="bsgx-grid">';
		foreach ( $items as $row ) {
			$out .= self::card_html( $row['post'], $row['miles'] );
		}
		return $out . '</div>';
	}

	public static function card_html( $post, $miles = null ) {
		$id       = $post->ID;
		$m        = function ( $k ) use ( $id ) {
			return get_post_meta( $id, $k, true );
		};
		$subtype  = $m( '_bsg_subtype' );
		$phone    = $m( '_bsg_phone' );
		$website  = $m( '_bsg_website' );
		$address  = $m( '_bsg_address' );
		$city     = $m( '_bsg_city' );
		$state    = $m( '_bsg_state' );
		$lat      = $m( '_bsg_lat' );
		$lng      = $m( '_bsg_lng' );
		$fav      = $m( '_bsg_favorite' );
		$featured = $m( '_bsg_featured' ) || $m( '_bsg_advertiser' );
		$img_url  = $m( '_bsg_image_url' );
		$is_ad    = 'full' === $m( '_bsg_adtype' );
		$cta      = $m( '_bsg_cta' );
		$facebook = $m( '_bsg_facebook' );
		$email    = $m( '_bsg_email' );
		$tags     = wp_get_post_terms( $id, self::TAX_TAG, array( 'fields' => 'names' ) );
		$tags     = is_wp_error( $tags ) ? array() : array_slice( $tags, 0, 4 );

		$maps = '';
		if ( $lat && $lng ) {
			$maps = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $lat . ',' . $lng );
		} elseif ( $address || $city ) {
			$maps = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( trim( $post->post_title . ', ' . $address . ', ' . $city . ', ' . $state, ', ' ) );
		}
		$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 );
		$line = $subtype;
		if ( null !== $miles && $miles > 0 ) {
			$line .= ( $line ? ' &middot; ' : '' ) . esc_html( number_format( $miles, 1 ) ) . ' miles away';
		}

		ob_start();
		?>
		<article class="bsgx-card<?php echo $featured ? ' is-featured' : ''; ?>">
			<div class="bsgx-img<?php echo ( $img_url && ! has_post_thumbnail( $post ) ) ? ' is-ad' : ''; ?><?php echo $is_ad ? ' is-full' : ''; ?>">
				<?php
				if ( has_post_thumbnail( $post ) ) {
					echo get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy', 'alt' => esc_attr( $post->post_title ) ) );
				} elseif ( $img_url ) {
					echo '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $post->post_title ) . '" loading="lazy">';
				}
				?>
				<?php if ( $fav ) : ?><span class="bsgx-badge">Local Favorite</span><?php endif; ?>
				<?php if ( $featured ) : ?><span class="bsgx-featured">Featured</span><?php endif; ?>
			</div>
			<div class="bsgx-body">
				<h3><?php echo esc_html( $post->post_title ); ?></h3>
				<?php if ( $line ) : ?><p class="bsgx-sub"><?php echo wp_kses_post( esc_html( $subtype ) . ( $subtype && null !== $miles && $miles > 0 ? ' &middot; ' : '' ) . ( null !== $miles && $miles > 0 ? esc_html( number_format( $miles, 1 ) ) . ' miles away' : '' ) ); ?></p><?php endif; ?>
				<?php if ( $tags ) : ?><p class="bsgx-tags"><?php echo esc_html( implode( ' &middot; ', $tags ) ); ?></p><?php endif; ?>
				<?php if ( $desc ) : ?><p class="bsgx-desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
				<?php if ( $address || $city ) : ?>
					<p class="bsgx-addr"><?php echo esc_html( trim( $address . ( $address && $city ? ', ' : '' ) . $city . ( $state ? ', ' . $state : '' ) ) ); ?></p>
				<?php endif; ?>
				<div class="bsgx-actions">
					<?php if ( $maps ) : ?><a href="<?php echo esc_url( $maps ); ?>" target="_blank" rel="noopener">Directions &rarr;</a><?php endif; ?>
					<?php if ( $website ) : ?><a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener">Website &rarr;</a><?php endif; ?>
					<?php if ( $phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">Call &rarr;</a><?php endif; ?>
					<?php if ( $facebook ) : ?><a href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener">Facebook &rarr;</a><?php endif; ?>
					<?php if ( $email && ! $phone ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>">Email &rarr;</a><?php endif; ?>
				</div>
				<?php if ( $cta && $website ) : ?><a class="bsgx-cta" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $cta ); ?></a><?php endif; ?>
			</div>
		</article>
		<?php
		return str_replace( '&amp;middot;', '&middot;', ob_get_clean() );
	}

	private static function styles() {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		return '<style>
#bsg-explore-page,.bsgx-embed{--navy:#12244d;--blue:#1f63e2;--line:#dde8f5;--muted:#6b7fa8;font-family:Figtree,system-ui,sans-serif;color:var(--navy)}
#bsg-explore-page{background:#f5f8fd;padding:28px 0 48px}
#bsg-explore-page *,.bsgx-embed *{box-sizing:border-box}
.bsgx-wrap{max-width:1280px;margin:0 auto;padding:0 48px}
.bsgx-crumbs{font-size:.8rem;color:var(--muted);margin-bottom:14px}
.bsgx-crumbs a{color:var(--blue);text-decoration:none}.bsgx-crumbs span{margin:0 4px}
.bsgx-search{display:flex;gap:8px;max-width:640px;margin:0 0 20px;padding:6px;background:#fff;border:1px solid var(--line);border-radius:12px}
.bsgx-search input{flex:1;min-width:0;border:0;outline:0;font:500 16px Figtree,system-ui,sans-serif;padding:8px 10px;color:var(--navy)}
.bsgx-search button{border:0;border-radius:9px;background:var(--blue);color:#fff;font:800 .9rem Figtree,system-ui,sans-serif;padding:10px 20px;cursor:pointer}
.bsgx-head h1{font-size:clamp(1.7rem,3vw,2.4rem);font-weight:900;letter-spacing:-.02em;line-height:1.1;margin:0;color:var(--navy)}
.bsgx-head p{color:var(--muted);margin:6px 0 18px;font-size:1rem}
.bsgx-note{color:var(--muted);font-size:.85rem;margin:0 0 14px}
.bsgx-cats{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.bsgx-cats a{padding:8px 14px;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--navy);font-weight:700;font-size:.85rem;text-decoration:none}
.bsgx-cats a.on,.bsgx-cats a:hover{background:var(--blue);border-color:var(--blue);color:#fff}
.bsgx-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;align-items:start}
.bsgx-card{display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;box-shadow:0 2px 14px rgba(31,99,226,.07)}
.bsgx-card.is-featured{border-color:#b9cef3}
.bsgx-img{position:relative;height:170px;background:linear-gradient(150deg,#5a9fc8,#2e72a0,#1d5a80)}
.bsgx-img img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bsgx-img.is-ad{height:auto;background:#f7faff}
.bsgx-img.is-ad img{position:static;display:block;width:100%;height:auto;max-height:560px;object-fit:contain}
.bsgx-img.is-ad:not(.is-full) img{aspect-ratio:1/1;object-fit:cover;max-height:none}
.bsgx-cta{display:block;margin-top:12px;padding:12px;border-radius:10px;background:var(--blue);color:#fff;text-align:center;font-weight:800;font-size:.85rem;text-decoration:none}
.bsgx-cta:hover{background:#1650c8;color:#fff}
.bsgx-badge{position:absolute;left:10px;top:10px;background:#f59e0b;color:#fff;font-size:.68rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;padding:4px 8px;border-radius:6px}
.bsgx-featured{position:absolute;right:10px;top:10px;background:rgba(18,36,77,.8);color:#fff;font-size:.65rem;font-weight:700;padding:3px 7px;border-radius:6px}
.bsgx-body{display:flex;flex-direction:column;gap:4px;flex:1;padding:14px 16px 16px}
.bsgx-body h3{font-size:1.02rem;font-weight:800;margin:0;color:var(--navy)}
.bsgx-sub{font-size:.8rem;color:var(--blue);font-weight:700;margin:0}
.bsgx-tags{font-size:.76rem;color:var(--muted);margin:0}
.bsgx-desc{font-size:.85rem;color:#5c6f96;line-height:1.5;margin:4px 0 0}
.bsgx-addr{font-size:.78rem;color:var(--muted);margin:2px 0 0}
.bsgx-actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:auto;padding-top:10px}
.bsgx-actions a{font-size:.84rem;font-weight:800;color:var(--blue);text-decoration:none}
.bsgx-dests{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.bsgx-dest{display:flex;flex-direction:column;gap:4px;padding:16px 18px;background:#fff;border:1px solid var(--line);border-radius:12px;text-decoration:none;color:var(--navy)}
.bsgx-dest strong{font-size:1rem;font-weight:800}.bsgx-dest span{font-size:.8rem;color:var(--muted)}
.bsgx-dest:hover{border-color:var(--blue)}
.bsgx-empty{background:#fff;border:1px dashed #c9d8ef;border-radius:14px;padding:30px 24px;text-align:center}
.bsgx-empty h2{font-size:1.3rem;font-weight:900;margin:0 0 6px;color:var(--navy)}
.bsgx-empty p{color:var(--muted);margin:0 0 14px}
.bsgx-empty-links{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-bottom:14px}
.bsgx-empty-links a{padding:8px 14px;border-radius:999px;background:#eef4ff;color:var(--blue);font-weight:700;font-size:.85rem;text-decoration:none}
.bsgx-all,.bsgx-back a{color:var(--blue);font-weight:800;text-decoration:none}
.bsgx-back{margin-top:26px}
@media(max-width:1000px){.bsgx-grid{grid-template-columns:repeat(2,1fr)}.bsgx-dests{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.bsgx-wrap{padding:0 16px}.bsgx-grid,.bsgx-dests{grid-template-columns:1fr}}
</style>';
	}

	/* =====================================================================
	 * SHORTCODE for guide pages
	 *   [bsg_local_listings category="food-dining"]                 businesses synced from THIS guide's map
	 *   [bsg_local_listings category="things-to-do" near="Cartersville, GA" limit="6"]   by distance
	 * ===================================================================== */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'category' => '', 'near' => '', 'limit' => 12 ), $atts, 'bsg_local_listings' );
		$cats = self::categories();
		$cat  = sanitize_title( $atts['category'] );
		if ( $cat && ! isset( $cats[ $cat ] ) ) {
			return '';
		}
		$out = self::styles() . '<div class="bsgx-embed">';
		if ( $atts['near'] ) {
			$place = self::resolve_where( $atts['near'] );
			if ( ! $place || ! $place['lat'] ) {
				return '';
			}
			$res = self::search( array( 'lat' => $place['lat'], 'lng' => $place['lng'], 'cat' => $cat, 'term_id' => $place['term_id'], 'limit' => (int) $atts['limit'] ) );
			$out .= $res['items'] ? self::grid( $res['items'] ) : '';
		} else {
			$args = array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $atts['limit'],
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array( array( 'key' => '_bsg_guide', 'value' => (string) get_the_ID() ) ),
			);
			if ( self::FAV === $cat ) {
				$args['meta_query'][] = array( 'key' => '_bsg_favorite', 'value' => '1' );
			} elseif ( $cat ) {
				$args['tax_query'] = array( array( 'taxonomy' => self::TAX_CAT, 'field' => 'slug', 'terms' => $cat ) );
			}
			$rows = array();
			foreach ( get_posts( $args ) as $p ) {
				$rows[] = array( 'post' => $p, 'miles' => null );
			}
			$out .= $rows ? self::grid( $rows ) : '';
		}
		return $out . '</div>';
	}

	/* =====================================================================
	 * LIVE SEARCH API (homepage search box)
	 *   GET /wp-json/bsg/v1/search?q=tacos&near=Stone Mountain Park&limit=8
	 * ===================================================================== */
	public static function rest_routes() {
		register_rest_route( 'bsg/v1', '/search', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'rest_search' ),
		) );
	}

	public static function rest_search( $r ) {
		$q     = sanitize_text_field( (string) $r->get_param( 'q' ) );
		$near  = sanitize_text_field( (string) $r->get_param( 'near' ) );
		$limit = min( 24, max( 1, (int) ( $r->get_param( 'limit' ) ? $r->get_param( 'limit' ) : 8 ) ) );
		$p     = self::parse_query( $q );
		/* a bare ZIP code is a place, not a keyword: show what's near it */
		if ( preg_match( '/^[0-9]{5}$/', trim( $q ) ) ) {
			$p = array( 'what' => '', 'where' => trim( $q ), 'cat' => '' );
		}
		$where = $p['where'] ? $p['where'] : $near;
		$place = $where ? self::resolve_where( $where ) : null;
		if ( $place && 'state' === $place['type'] ) {
			$place = null;
		}
		$opts = array( 'what' => $p['what'], 'cat' => $p['cat'], 'limit' => $limit );
		if ( $place ) {
			$opts['lat']     = $place['lat'];
			$opts['lng']     = $place['lng'];
			$opts['term_id'] = $place['term_id'];
			$opts['guide']   = 'guide' === $place['type'] ? $place['page_id'] : 0;
		}
		/* keyword anywhere, or anything (all categories) near a place */
		$res   = ( $p['what'] || $place ) ? self::search( $opts ) : array( 'items' => array(), 'radius' => 0 );
		$items = array();
		foreach ( $res['items'] as $row ) {
			$items[] = self::item_json( $row['post'], $place ? $row['miles'] : null );
		}
		$full = $q . ( $near && ! $p['where'] ? ' near ' . $near : '' );
		return rest_ensure_response( array(
			'query'  => $q,
			'near'   => $place ? $place['name'] : '',
			'radius' => $place ? (int) $res['radius'] : 0,
			'items'  => $items,
			'more'   => self::search_url( $full ),
		) );
	}

	/** A business as JSON for the live results. */
	public static function item_json( $post, $miles = null ) {
		$id  = $post->ID;
		$m   = function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, $k, true );
		};
		$img = has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'medium_large' ) : $m( '_bsg_image_url' );
		$lat = $m( '_bsg_lat' );
		$lng = $m( '_bsg_lng' );
		$city = $m( '_bsg_city' );
		$addr = $m( '_bsg_address' );
		$maps = $lat && $lng ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $lat . ',' . $lng )
			: ( $addr ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $post->post_title . ', ' . $addr ) : '' );
		$guides = array_filter( array_map( 'intval', get_post_meta( $id, '_bsg_guide' ) ) );
		$guide  = $guides ? reset( $guides ) : 0;
		$tags   = wp_get_post_terms( $id, self::TAX_TAG, array( 'fields' => 'names' ) );
		return array(
			'name'     => $post->post_title,
			'type'     => $m( '_bsg_subtype' ),
			'where'    => $city ? $city : $addr,
			'miles'    => null === $miles ? null : round( $miles, 1 ),
			'image'    => $img ? $img : '',
			'full_ad'  => 'full' === $m( '_bsg_adtype' ),
			'website'  => $m( '_bsg_website' ),
			'facebook' => $m( '_bsg_facebook' ),
			'phone'    => $m( '_bsg_phone' ),
			'maps'     => $maps,
			'featured' => (bool) ( $m( '_bsg_featured' ) || $m( '_bsg_advertiser' ) ),
			'favorite' => (bool) $m( '_bsg_favorite' ),
			'tags'     => is_wp_error( $tags ) ? array() : array_slice( $tags, 0, 3 ),
			'guide'    => $guide ? array( 'title' => get_the_title( $guide ), 'url' => get_permalink( $guide ) ) : null,
		);
	}

	/* =====================================================================
	 * SESSION (homepage "Exploring Cartersville, GA")
	 * ===================================================================== */
	public static function session_script() {
		$data = null;
		$guide_mode = 'destination' !== self::settings()['camp_mode'];
		if ( self::is_explore() ) {
			$req = self::request();
			if ( 'guide' === $req['mode'] ) {
				$data = array( 'v' => 2, 'kind' => 'campground', 'name' => $req['place']['name'], 'url' => self::guide_url( $req['place']['slug'] ), 'page' => get_permalink( $req['place']['page_id'] ), 'via' => '' );
			} elseif ( 'place' === $req['mode'] && $req['place']['state'] ) {
				$p    = $req['place'];
				$data = array(
					'v'    => 2,
					'kind' => 'state' === $p['type'] ? 'state' : 'city',
					'name' => $p['name'],
					'url'  => self::url( $p['state'], $p['city_slug'] ),
					'via'  => '',
				);
			}
		}
		if ( isset( $_GET['bsg_place'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$posts = get_posts( array( 'name' => sanitize_title( wp_unslash( $_GET['bsg_place'] ) ), 'post_type' => 'any', 'posts_per_page' => 1 ) ); // phpcs:ignore
			if ( $posts && $guide_mode ) {
				$data = array( 'v' => 2, 'kind' => 'campground', 'name' => wp_strip_all_tags( get_the_title( $posts[0] ) ), 'url' => home_url( '/' . self::BASE . '/search/' ), 'page' => get_permalink( $posts[0] ), 'via' => '' );
			} elseif ( $posts ) {
				$term = get_term( (int) get_post_meta( $posts[0]->ID, '_bsg_dest', true ), self::TAX_DEST );
				if ( $term && ! is_wp_error( $term ) ) {
					$st = get_term_meta( $term->term_id, 'bsg_state', true );
					if ( $st ) {
						$data = array(
							'v'    => 2,
							'kind' => 'city',
							'name' => $term->name . ', ' . self::$states[ $st ][0],
							'url'  => self::url( $st, sanitize_title( $term->name ) ),
							'via'  => wp_strip_all_tags( get_the_title( $posts[0] ) ),
						);
					}
				}
			}
		}
		if ( $data ) {
			echo '<script>try{sessionStorage.setItem(' . wp_json_encode( self::SESSION ) . ',JSON.stringify(' . wp_json_encode( $data ) . '));}catch(e){}</script>' . "\n";
		}
	}

	/* =====================================================================
	 * SYNC: Google My Maps on guide pages -> Local Businesses
	 * ===================================================================== */
	/**
	 * Every page that holds a BSG_DATA business list. array( post id => array( items ) )
	 * Read straight from the database (page content + Beaver Builder layout data).
	 */
	public static function discover_guides() {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( 'BSG_DATA' ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID AS pid, post_content AS txt FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post','gd_place') AND post_content LIKE %s", $like ) ); // phpcs:ignore
		$meta = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id AS pid, m.meta_value AS txt FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status = 'publish' AND p.post_type IN ('page','post','gd_place') AND m.meta_key NOT LIKE %s AND m.meta_value LIKE %s", '%draft%', $like ) ); // phpcs:ignore
		$out  = array();
		foreach ( array_merge( (array) $rows, (array) $meta ) as $r ) {
			$pid = (int) $r->pid;
			if ( ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = array();
			}
			foreach ( self::extract_items( (string) $r->txt ) as $it ) {
				$out[ $pid ][ sanitize_title( isset( $it['name'] ) ? $it['name'] : '' ) ] = $it;
			}
		}
		foreach ( $out as $pid => $items ) {
			$out[ $pid ] = array_values( array_filter( $items, function ( $it ) {
				return ! empty( $it['name'] );
			} ) );
		}
		return $out;
	}

	/** Pull every "BSG_DATA = [ ... ]" list out of a block of text. */
	public static function extract_items( $text ) {
		$items = array();
		$from  = 0;
		while ( false !== ( $pos = strpos( $text, 'BSG_DATA', $from ) ) ) {
			$from = $pos + 8;
			if ( ! preg_match( '/\G\s*=\s*\[/', $text, $m, 0, $from ) ) {
				continue;
			}
			$start   = $from + strlen( $m[0] ) - 1;
			$literal = self::slice_literal( $text, $start );
			if ( '' === $literal ) {
				continue;
			}
			$data = json_decode( self::js_to_json( $literal ), true );
			if ( is_array( $data ) ) {
				foreach ( $data as $it ) {
					if ( is_array( $it ) ) {
						$items[] = $it;
					}
				}
			}
		}
		return $items;
	}

	/** From an opening [ or {, return the text up to its matching close (string/comment aware). */
	private static function slice_literal( $s, $start ) {
		$n     = strlen( $s );
		$depth = 0;
		for ( $i = $start; $i < $n; $i++ ) {
			$c = $s[ $i ];
			if ( '"' === $c || "'" === $c || '`' === $c ) {
				for ( $i++; $i < $n && $s[ $i ] !== $c; $i++ ) {
					if ( '\\' === $s[ $i ] ) {
						$i++;
					}
				}
				continue;
			}
			if ( '/' === $c && $i + 1 < $n && '/' === $s[ $i + 1 ] ) {
				$e = strpos( $s, "\n", $i );
				$i = false === $e ? $n : $e;
				continue;
			}
			if ( '/' === $c && $i + 1 < $n && '*' === $s[ $i + 1 ] ) {
				$e = strpos( $s, '*/', $i + 2 );
				$i = false === $e ? $n : $e + 1;
				continue;
			}
			if ( '[' === $c || '{' === $c ) {
				$depth++;
			} elseif ( ']' === $c || '}' === $c ) {
				$depth--;
				if ( 0 === $depth ) {
					return substr( $s, $start, $i - $start + 1 );
				}
			}
		}
		return '';
	}

	/**
	 * Convert a JavaScript object/array literal to JSON:
	 * removes // and /* comments, quotes bare keys, turns 'single' into "double" quotes,
	 * drops trailing commas. URLs inside strings ("https://...") are left untouched.
	 */
	public static function js_to_json( $s ) {
		$out = '';
		$n   = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = $s[ $i ];
			if ( '/' === $c && $i + 1 < $n && '/' === $s[ $i + 1 ] ) {
				$e = strpos( $s, "\n", $i );
				$i = false === $e ? $n : $e;
				continue;
			}
			if ( '/' === $c && $i + 1 < $n && '*' === $s[ $i + 1 ] ) {
				$e = strpos( $s, '*/', $i + 2 );
				$i = false === $e ? $n : $e + 1;
				continue;
			}
			if ( '"' === $c || "'" === $c || '`' === $c ) {
				$q   = $c;
				$buf = '';
				for ( $i++; $i < $n && $s[ $i ] !== $q; $i++ ) {
					$ch = $s[ $i ];
					if ( '\\' === $ch && $i + 1 < $n ) {
						$nx   = $s[ ++$i ];
						$buf .= ( "'" === $nx || '`' === $nx ) ? $nx : '\\' . $nx;
					} elseif ( '"' === $ch ) {
						$buf .= '\\"';
					} elseif ( "\n" === $ch ) {
						$buf .= '\\n';
					} elseif ( "\r" === $ch ) {
						continue;
					} elseif ( "\t" === $ch ) {
						$buf .= '\\t';
					} else {
						$buf .= $ch;
					}
				}
				$out .= '"' . $buf . '"';
				continue;
			}
			if ( ctype_alpha( $c ) || '_' === $c || '$' === $c ) {
				$w = '';
				while ( $i < $n && ( ctype_alnum( $s[ $i ] ) || '_' === $s[ $i ] || '$' === $s[ $i ] ) ) {
					$w .= $s[ $i++ ];
				}
				$i--;
				$out .= in_array( $w, array( 'true', 'false', 'null' ), true ) ? $w : '"' . $w . '"';
				continue;
			}
			if ( ']' === $c || '}' === $c ) {
				$out = rtrim( $out );
				if ( ',' === substr( $out, -1 ) ) {
					$out = substr( $out, 0, -1 );
				}
			}
			$out .= $c;
		}
		return $out;
	}

	private static function ensure_category( $slug, $label ) {
		if ( ! term_exists( $slug, self::TAX_CAT ) ) {
			wp_insert_term( $label, self::TAX_CAT, array( 'slug' => $slug ) );
		}
		return $slug;
	}

	private static function format_phone( $p ) {
		$d = preg_replace( '/\D/', '', $p );
		if ( 11 === strlen( $d ) && '1' === $d[0] ) {
			$d = substr( $d, 1 );
		}
		return 10 === strlen( $d ) ? '(' . substr( $d, 0, 3 ) . ') ' . substr( $d, 3, 3 ) . '-' . substr( $d, 6 ) : trim( $p );
	}

	/** Normalise one guide ad into business fields. */
	public static function item_fields( $it ) {
		$get  = function ( $k ) use ( $it ) {
			return isset( $it[ $k ] ) && is_scalar( $it[ $k ] ) ? trim( (string) $it[ $k ] ) : '';
		};
		$mapq = $get( 'mapQ' );
		$lat  = 0;
		$lng  = 0;
		if ( preg_match( '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $mapq, $m ) ) {
			$lat = (float) $m[1];
			$lng = (float) $m[2];
		} elseif ( preg_match( '/[?&](?:q|query|destination)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/', $mapq, $m ) ) {
			$lat = (float) $m[1];
			$lng = (float) $m[2];
		}
		$first   = trim( explode( "\xC2\xB7", $get( 'address' ) )[0] );
		$is_addr = (bool) preg_match( '/\d/', $first );
		$phone   = $get( 'phone' );
		$fb      = '';
		if ( preg_match( '/^https?:/i', $phone ) ) {
			$fb    = $phone;
			$phone = '';
		} else {
			$phone = self::format_phone( preg_replace( '/^tel:?/i', '', $phone ) );
		}
		$web = ( ! empty( $it['hideWeb'] ) && 'false' !== $it['hideWeb'] ) ? '' : $get( 'website' );
		$kw  = isset( $it['keywords'] ) ? ( is_array( $it['keywords'] ) ? $it['keywords'] : explode( ',', (string) $it['keywords'] ) ) : array();
		$kw  = array_values( array_filter( array_map( 'trim', array_map( 'strval', $kw ) ) ) );
		$cat = strtolower( $get( 'category' ) );
		$map = isset( self::$guide_cats[ $cat ] ) ? self::$guide_cats[ $cat ] : null;
		return array(
			'name'     => wp_strip_all_tags( $get( 'name' ) ),
			'cat'      => $map,
			'type'     => $get( 'tag' ),
			'address'  => $is_addr ? $first : '',
			'about'    => $is_addr ? '' : $first,
			'phone'    => $phone,
			'facebook' => $fb,
			'website'  => $web,
			'email'    => $get( 'email' ),
			'lat'      => $lat,
			'lng'      => $lng,
			'image'    => $get( 'image' ),
			'promo'    => $get( 'promo' ),
			'cta'      => $get( 'cta' ),
			'full_ad'  => 'full' === strtolower( $get( 'adType' ) ),
			'keywords' => $kw,
		);
	}

	/** Create or update one business from a guide ad. The guide is the source of truth unless the business was edited in the admin. */
	private static function upsert_item( $it, $pid, $run ) {
		$f = self::item_fields( $it );
		if ( '' === $f['name'] ) {
			return '';
		}
		$key      = sanitize_title( $f['name'] ) . '|' . ( $f['lat'] ? round( $f['lat'], 3 ) . ',' . round( $f['lng'], 3 ) : 'nomap' );
		$existing = get_posts( array( 'post_type' => self::CPT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_bsg_key', 'meta_value' => $key ) ); // phpcs:ignore
		$desc     = $f['promo'] ? $f['promo'] : $f['about'];

		if ( $existing ) {
			$id     = $existing[0];
			$status = 'updated';
			$manual = (bool) get_post_meta( $id, '_bsg_manual', true );
			if ( ! $manual ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish', 'post_title' => $f['name'], 'post_excerpt' => $desc ) );
			}
		} else {
			$id = wp_insert_post( array(
				'post_type'    => self::CPT,
				'post_status'  => 'publish',
				'post_title'   => $f['name'],
				'post_excerpt' => $desc,
			) );
			if ( is_wp_error( $id ) || ! $id ) {
				return '';
			}
			$status = 'created';
			$manual = false;
			update_post_meta( $id, '_bsg_key', $key );
			update_post_meta( $id, '_bsg_synced', '1' );
		}

		$fields = array(
			'_bsg_subtype'   => $f['type'],
			'_bsg_address'   => $f['address'],
			'_bsg_phone'     => $f['phone'],
			'_bsg_facebook'  => esc_url_raw( $f['facebook'] ),
			'_bsg_website'   => esc_url_raw( $f['website'] ),
			'_bsg_email'     => sanitize_email( $f['email'] ),
			'_bsg_image_url' => esc_url_raw( $f['image'] ),
			'_bsg_promo'     => $f['promo'],
			'_bsg_cta'       => $f['cta'],
			'_bsg_adtype'    => $f['full_ad'] ? 'full' : '',
			'_bsg_keywords'  => implode( ', ', $f['keywords'] ),
		);
		if ( $f['lat'] ) {
			$fields['_bsg_lat'] = $f['lat'];
			$fields['_bsg_lng'] = $f['lng'];
		}
		foreach ( $fields as $k => $v ) {
			/* guide data wins, except on businesses edited in the admin (then only empty fields are filled) */
			if ( ! $manual || '' === (string) get_post_meta( $id, $k, true ) ) {
				update_post_meta( $id, $k, $v );
			}
		}
		if ( 'created' === $status ) {
			update_post_meta( $id, '_bsg_advertiser', '1' );
			update_post_meta( $id, '_bsg_featured', $f['full_ad'] ? '1' : '0' );
		}
		update_post_meta( $id, '_bsg_last_seen', $run );

		$have = array_map( 'intval', get_post_meta( $id, '_bsg_guide' ) );
		if ( ! in_array( (int) $pid, $have, true ) ) {
			add_post_meta( $id, '_bsg_guide', (string) (int) $pid );
		}
		/* businesses without map coordinates (virtual / online) still get the guide's destination */
		if ( ! $f['lat'] ) {
			$dest = (int) get_post_meta( $pid, '_bsg_dest', true );
			if ( $dest ) {
				wp_set_object_terms( $id, array( $dest ), self::TAX_DEST, true );
			}
		}

		if ( $f['cat'] && ( ! $manual || ! wp_get_object_terms( $id, self::TAX_CAT, array( 'fields' => 'ids' ) ) ) ) {
			wp_set_object_terms( $id, array( self::ensure_category( $f['cat'][0], $f['cat'][1] ) ), self::TAX_CAT, false );
		}
		self::auto_tag( $id, $f['name'] . ' ' . $f['type'] . ' ' . implode( ' , ', $f['keywords'] ) . ' ' . $desc );
		return $status;
	}

	/** Find every My Map id used on the site, with the page it sits on. array( mid => array(post ids) ) */
	public static function discover_maps() {
		global $wpdb;
		$found = array();
		$like  = '%' . $wpdb->esc_like( '/maps/d/' ) . '%';
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT ID AS pid, post_content AS txt FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision','nav_menu_item') AND post_content LIKE %s", $like ) ); // phpcs:ignore
		$meta  = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id AS pid, m.meta_value AS txt FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status = 'publish' AND p.post_type <> 'revision' AND m.meta_key NOT LIKE %s AND m.meta_value LIKE %s", '%draft%', $like ) ); // phpcs:ignore
		foreach ( array_merge( (array) $rows, (array) $meta ) as $r ) {
			if ( preg_match_all( '/maps\/d\/[^"\'\s<>]*?[?&](?:amp;)?mid=([A-Za-z0-9_\-]+)/', $r->txt, $m ) ) {
				foreach ( $m[1] as $mid ) {
					$found[ $mid ][ (int) $r->pid ] = true;
				}
			}
		}
		/* extra maps typed in Settings: "MAP-ID | page slug (optional)" */
		foreach ( preg_split( '/\r\n|\r|\n/', self::settings()['maps'] ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( empty( $parts[0] ) ) {
				continue;
			}
			$mid = preg_match( '/mid=([A-Za-z0-9_\-]+)/', $parts[0], $mm ) ? $mm[1] : $parts[0];
			$pid = 0;
			if ( ! empty( $parts[1] ) ) {
				$pg  = get_page_by_path( sanitize_title( $parts[1] ) );
				$pid = $pg ? $pg->ID : 0;
			}
			$found[ $mid ][ $pid ] = true;
		}
		$out = array();
		foreach ( $found as $mid => $pids ) {
			$ids = array();
			foreach ( array_keys( $pids ) as $pid ) {
				$pt = $pid ? get_post_type( $pid ) : '';
				if ( $pid && in_array( $pt, array( 'page', 'post', 'gd_place' ), true ) ) {
					$ids[] = $pid;
				}
			}
			$out[ $mid ] = $ids;
		}
		return $out;
	}

	/** Read one public My Map. Returns array of pins or WP_Error. */
	public static function read_map( $mid ) {
		$res = wp_remote_get( 'https://www.google.com/maps/d/kml?forcekml=1&mid=' . rawurlencode( $mid ), array( 'timeout' => 25 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = wp_remote_retrieve_body( $res );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || false === strpos( $body, '<kml' ) ) {
			return new WP_Error( 'bsg_map', 'Map is not public or could not be read (set it to "Anyone with the link can view").' );
		}
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_string( $body );
		libxml_use_internal_errors( $prev );
		if ( ! $xml ) {
			return new WP_Error( 'bsg_map', 'Map data could not be parsed.' );
		}
		$xml->registerXPathNamespace( 'k', 'http://www.opengis.net/kml/2.2' );
		$pins = array();
		foreach ( $xml->xpath( '//k:Placemark' ) as $pm ) {
			$pm->registerXPathNamespace( 'k', 'http://www.opengis.net/kml/2.2' );
			$coords = $pm->xpath( './/k:Point/k:coordinates' );
			if ( ! $coords ) {
				continue; /* lines/areas are not businesses */
			}
			$c = explode( ',', trim( (string) $coords[0] ) );
			if ( count( $c ) < 2 ) {
				continue;
			}
			$folder = $pm->xpath( 'ancestor::k:Folder[1]/k:name' );
			$data   = array();
			foreach ( $pm->xpath( './/k:ExtendedData/k:Data' ) as $d ) {
				$d->registerXPathNamespace( 'k', 'http://www.opengis.net/kml/2.2' );
				$v = $d->xpath( 'k:value' );
				$data[ strtolower( (string) $d['name'] ) ] = $v ? trim( (string) $v[0] ) : '';
			}
			$pins[] = array(
				'name'   => trim( (string) $pm->name ),
				'desc'   => trim( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), "\n", (string) $pm->description ) ) ),
				'lng'    => (float) $c[0],
				'lat'    => (float) $c[1],
				'layer'  => $folder ? trim( (string) $folder[0] ) : '',
				'data'   => $data,
			);
		}
		return $pins;
	}

	private static function pick( $data, $keys ) {
		foreach ( $keys as $k ) {
			if ( ! empty( $data[ $k ] ) ) {
				return $data[ $k ];
			}
		}
		return '';
	}

	/** Run the sync. Returns a log array. */
	public static function sync() {
		@set_time_limit( 600 ); // phpcs:ignore
		$start = time();
		$log   = array( 'time' => current_time( 'mysql' ), 'guides' => array(), 'maps' => array(), 'created' => 0, 'updated' => 0, 'unpublished' => 0 );

		/* 1. business ads on the Local Area Guides (BSG_DATA) */
		$guides = self::discover_guides();
		foreach ( $guides as $pid => $items ) {
			$entry = array( 'page' => get_the_title( $pid ), 'url' => get_permalink( $pid ), 'ads' => count( $items ) );
			if ( ! $items ) {
				$entry['error'] = 'The business list on this page could not be read.';
			}
			foreach ( $items as $it ) {
				$r = self::upsert_item( $it, $pid, $start );
				if ( 'created' === $r ) {
					$log['created']++;
				} elseif ( 'updated' === $r ) {
					$log['updated']++;
				}
			}
			$log['guides'][] = $entry;
		}

		/* 2. optional: Google My Maps pins */
		$maps = self::settings()['read_maps'] ? self::discover_maps() : array();

		foreach ( $maps as $mid => $pages ) {
			$pins  = self::read_map( $mid );
			$entry = array( 'mid' => $mid, 'pages' => array_map( 'get_the_title', $pages ) );
			if ( is_wp_error( $pins ) ) {
				$entry['error']  = $pins->get_error_message();
				$log['maps'][]   = $entry;
				continue;
			}
			$entry['pins'] = count( $pins );
			foreach ( $pins as $pin ) {
				if ( '' === $pin['name'] ) {
					continue;
				}
				$r = self::upsert_pin( $pin, $pages, $start );
				if ( 'created' === $r ) {
					$log['created']++;
				} elseif ( 'updated' === $r ) {
					$log['updated']++;
				}
			}
			$log['maps'][] = $entry;
		}

		/* businesses that came from a guide/map but are no longer on any */
		if ( self::settings()['unpublish'] && ( $maps || $guides ) ) {
			$gone = get_posts( array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => '_bsg_synced', 'value' => '1' ),
					array( 'key' => '_bsg_manual', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_bsg_last_seen', 'value' => $start, 'compare' => '<', 'type' => 'NUMERIC' ),
				),
			) );
			$all = array_merge( $log['maps'], $log['guides'] );
			$ok  = count( array_filter( $all, function ( $e ) {
				return empty( $e['error'] );
			} ) );
			if ( $ok === count( $all ) ) { /* only when every source was read fine */
				foreach ( $gone as $id ) {
					wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
					$log['unpublished']++;
				}
			}
		}
		update_option( self::LOG, $log, false );
		return $log;
	}

	/** Create or update one business from a map pin. */
	private static function upsert_pin( $pin, $pages, $run ) {
		$key      = sanitize_title( $pin['name'] ) . '|' . round( $pin['lat'], 3 ) . ',' . round( $pin['lng'], 3 );
		$existing = get_posts( array( 'post_type' => self::CPT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_bsg_key', 'meta_value' => $key ) ); // phpcs:ignore
		$d        = $pin['data'];
		$desc     = self::pick( $d, array( 'description', 'desc', 'notes' ) );
		if ( '' === $desc ) {
			$desc = $pin['desc'];
		}

		if ( $existing ) {
			$id     = $existing[0];
			$status = 'updated';
			if ( 'draft' === get_post_status( $id ) && ! get_post_meta( $id, '_bsg_manual', true ) ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			}
		} else {
			$id = wp_insert_post( array(
				'post_type'    => self::CPT,
				'post_status'  => 'publish',
				'post_title'   => wp_strip_all_tags( $pin['name'] ),
				'post_content' => wp_kses_post( $desc ),
			) );
			if ( is_wp_error( $id ) || ! $id ) {
				return '';
			}
			$status = 'created';
			update_post_meta( $id, '_bsg_key', $key );
			update_post_meta( $id, '_bsg_synced', '1' );
		}

		/* fill only empty fields, so edits made in the admin are never overwritten */
		$fill = array(
			'_bsg_lat'       => $pin['lat'],
			'_bsg_lng'       => $pin['lng'],
			'_bsg_address'   => self::pick( $d, array( 'address', 'street', 'street address', 'location' ) ),
			'_bsg_city'      => self::pick( $d, array( 'city', 'town' ) ),
			'_bsg_state'     => self::pick( $d, array( 'state' ) ),
			'_bsg_zip'       => self::pick( $d, array( 'zip', 'zip code', 'zipcode', 'postal code' ) ),
			'_bsg_phone'     => self::pick( $d, array( 'phone', 'phone number', 'telephone' ) ),
			'_bsg_website'   => esc_url_raw( self::pick( $d, array( 'website', 'url', 'web', 'link' ) ) ),
			'_bsg_subtype'   => self::pick( $d, array( 'type', 'business type', 'category', 'cuisine' ) ),
			'_bsg_image_url' => esc_url_raw( strtok( self::pick( $d, array( 'gx_media_links', 'image', 'photo' ) ), ' ' ) ),
		);
		foreach ( $fill as $k => $v ) {
			if ( '' !== (string) $v && '' === (string) get_post_meta( $id, $k, true ) ) {
				update_post_meta( $id, $k, $v );
			}
		}
		update_post_meta( $id, '_bsg_last_seen', $run );

		/* guide relationships (a business can sit on several guides) */
		$have = array_map( 'intval', get_post_meta( $id, '_bsg_guide' ) );
		foreach ( $pages as $pid ) {
			if ( ! in_array( (int) $pid, $have, true ) ) {
				add_post_meta( $id, '_bsg_guide', (string) (int) $pid );
			}
		}

		/* category from the map layer (or type / name) when none is set */
		$cats = wp_get_object_terms( $id, self::TAX_CAT, array( 'fields' => 'ids' ) );
		if ( empty( $cats ) || is_wp_error( $cats ) ) {
			$guess = self::guess_category( $pin['layer'] . ' ' . $fill['_bsg_subtype'] );
			if ( ! $guess ) {
				$guess = self::guess_category( $pin['name'] . ' ' . $desc );
			}
			if ( $guess ) {
				wp_set_object_terms( $id, array( $guess ), self::TAX_CAT, false );
			}
		}

		self::auto_tag( $id, $pin['name'] . ' ' . $pin['layer'] . ' ' . $fill['_bsg_subtype'] . ' ' . $desc );
		return $status;
	}

	/* =====================================================================
	 * ADMIN: settings page
	 * ===================================================================== */
	public static function admin_menu() {
		add_submenu_page( 'edit.php?post_type=' . self::CPT, 'Search & Sync Settings', 'Search & Sync', 'manage_options', 'bsg-local-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function settings_page() {
		$s    = self::settings();
		$log  = get_option( self::LOG );
		$cats = self::categories();
		?>
		<div class="wrap">
			<h1>BlueSpot Local Guide: Search &amp; Sync</h1>
			<?php if ( isset( $_GET['synced'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success"><p>Sync finished. See the results below.</p></div>
			<?php elseif ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success"><p>Settings saved.</p></div>
			<?php endif; ?>

			<h2>Sync from your Local Area Guides</h2>
			<p>Every night the plugin reads the business ads on every Local Area Guide and creates or updates a Local Business for each one, with its category, type, address, phone, website, map location, ad image and keywords. New advertisers only need to be added to the guide.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bsg_local_sync_now' ); ?>
				<input type="hidden" name="action" value="bsg_local_sync_now">
				<?php submit_button( 'Run sync now', 'primary', 'submit', false ); ?>
				<span class="description">&nbsp; Next automatic sync: <?php echo esc_html( wp_next_scheduled( self::CRON ) ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', wp_next_scheduled( self::CRON ) ) ) : 'not scheduled' ); ?></span>
			</form>

			<?php if ( is_array( $log ) ) : ?>
				<h3>Last sync: <?php echo esc_html( $log['time'] ); ?></h3>
				<table class="widefat striped" style="max-width:1000px;margin-bottom:14px">
					<thead><tr><th>Local Area Guide</th><th>Business ads read</th></tr></thead>
					<tbody>
					<?php foreach ( isset( $log['guides'] ) ? $log['guides'] : array() as $e ) : ?>
						<tr><td><a href="<?php echo esc_url( $e['url'] ); ?>" target="_blank"><?php echo esc_html( $e['page'] ); ?></a></td>
						<td><?php echo isset( $e['error'] ) ? '<span style="color:#b32d2e">' . esc_html( $e['error'] ) . '</span>' : (int) $e['ads']; ?></td></tr>
					<?php endforeach; ?>
					<?php if ( empty( $log['guides'] ) ) : ?><tr><td colspan="2">No guide pages with business ads (BSG_DATA) were found.</td></tr><?php endif; ?>
					</tbody>
				</table>
				<p><strong><?php echo (int) $log['created']; ?></strong> created &middot; <strong><?php echo (int) $log['updated']; ?></strong> updated &middot; <strong><?php echo (int) $log['unpublished']; ?></strong> unpublished (removed from maps)</p>
				<table class="widefat striped" style="max-width:1000px">
					<thead><tr><th>Map</th><th>Found on</th><th>Result</th></tr></thead>
					<tbody>
					<?php foreach ( $log['maps'] as $e ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( 'https://www.google.com/maps/d/viewer?mid=' . $e['mid'] ); ?>" target="_blank"><?php echo esc_html( $e['mid'] ); ?></a></td>
							<td><?php echo esc_html( implode( ', ', array_filter( $e['pages'] ) ) ); ?></td>
							<td><?php echo isset( $e['error'] ) ? '<span style="color:#b32d2e">' . esc_html( $e['error'] ) . '</span>' : esc_html( $e['pins'] . ' pins' ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $log['maps'] ) ) : ?>
						<tr><td colspan="3">No Google My Maps were found on the site. Add map IDs below.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bsg_local_save' ); ?>
				<input type="hidden" name="action" value="bsg_local_save">

				<h2>Search radius (miles) by category</h2>
				<p>Results start within this distance and widen automatically, up to double, when fewer than the minimum are found.</p>
				<table class="form-table" role="presentation">
					<?php foreach ( array_merge( $cats, array( 'all' => 'Free-text search (no category)' ) ) as $slug => $label ) : ?>
						<tr><th><?php echo esc_html( $label ); ?></th><td><input type="number" min="1" max="200" name="radius[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( isset( $s['radius'][ $slug ] ) ? $s['radius'][ $slug ] : 25 ); ?>"> miles</td></tr>
					<?php endforeach; ?>
					<tr><th>Minimum results before widening</th><td><input type="number" min="1" max="50" name="min" value="<?php echo esc_attr( $s['min'] ); ?>"></td></tr>
				</table>

				<h2>Search tag dictionary</h2>
				<p>One tag per line: <code>Tag: keyword, keyword, keyword</code>. Businesses get the tag automatically when their name, type, map layer or description contains a keyword, and searching any keyword finds the whole group (so <em>tacos</em> finds Mexican restaurants).</p>
				<textarea name="dictionary" rows="16" class="large-text code"><?php echo esc_textarea( $s['dictionary'] ); ?></textarea>

				<h2>Extra maps (optional)</h2>
				<p>Maps are found automatically. Add any others here, one per line: <code>MAP-ID or map link | guide page slug</code></p>
				<textarea name="maps" rows="4" class="large-text code"><?php echo esc_textarea( $s['maps'] ); ?></textarea>

				<h2>Other</h2>
				<table class="form-table" role="presentation">
					<tr><th>Google Geocoding API key (optional)</th><td><input type="text" class="regular-text" name="google_key" value="<?php echo esc_attr( $s['google_key'] ); ?>"><p class="description">Leave empty to use free OpenStreetMap geocoding.</p></td></tr>
					<tr><th>When a campground is chosen</th><td>
						<label><input type="radio" name="camp_mode" value="nearby" <?php checked( 'nearby', $s['camp_mode'] ); ?>> Search all business ads near the campground, matched by keywords <em>(&ldquo;Food &amp; Dining near Stone Mountain Park&rdquo;)</em></label><br>
						<label><input type="radio" name="camp_mode" value="guide" <?php checked( 'guide', $s['camp_mode'] ); ?>> Show only that campground&rsquo;s own Local Area Guide businesses <em>(&ldquo;Food &amp; Dining Near Forsyth Station RV Resort&rdquo;)</em></label><br>
						<label><input type="radio" name="camp_mode" value="destination" <?php checked( 'destination', $s['camp_mode'] ); ?>> Use the campground&rsquo;s city and show everything nearby <em>(&ldquo;Food &amp; Dining near Forsyth, Georgia&rdquo;)</em></label>
						<p class="description">Must match <code>CAMP_RESULTS</code> at the top of the homepage module script.</p></td></tr>
					<tr><th>Google My Maps</th><td><label><input type="checkbox" name="read_maps" value="1" <?php checked( $s['read_maps'] ); ?>> Also create businesses from the pins on the guides&rsquo; Google My Maps (usually not needed: the guide ads are read automatically)</label></td></tr>
					<tr><th>Removed pins</th><td><label><input type="checkbox" name="unpublish" value="1" <?php checked( $s['unpublish'] ); ?>> Switch a synced business to Draft when it disappears from every map (businesses edited in the admin are never touched)</label></td></tr>
				</table>
				<?php submit_button( 'Save settings' ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'bsg_local_save' ) ) {
			wp_die( 'Not allowed.' );
		}
		$radius = array();
		foreach ( (array) ( isset( $_POST['radius'] ) ? wp_unslash( $_POST['radius'] ) : array() ) as $k => $v ) { // phpcs:ignore
			$radius[ sanitize_key( $k ) ] = max( 1, min( 200, (float) $v ) );
		}
		update_option( self::OPT, array(
			'radius'     => $radius,
			'min'        => isset( $_POST['min'] ) ? (int) $_POST['min'] : 6,
			'dictionary' => isset( $_POST['dictionary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dictionary'] ) ) : '',
			'maps'       => isset( $_POST['maps'] ) ? sanitize_textarea_field( wp_unslash( $_POST['maps'] ) ) : '',
			'google_key' => isset( $_POST['google_key'] ) ? sanitize_text_field( wp_unslash( $_POST['google_key'] ) ) : '',
			'unpublish'  => ! empty( $_POST['unpublish'] ),
			'read_maps'  => ! empty( $_POST['read_maps'] ),
			'camp_mode'  => isset( $_POST['camp_mode'] ) && in_array( $_POST['camp_mode'], array( 'guide', 'destination' ), true ) ? sanitize_key( $_POST['camp_mode'] ) : 'nearby',
		) );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::CPT . '&page=bsg-local-settings&saved=1' ) );
		exit;
	}

	public static function sync_now() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'bsg_local_sync_now' ) ) {
			wp_die( 'Not allowed.' );
		}
		self::sync();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::CPT . '&page=bsg-local-settings&synced=1' ) );
		exit;
	}

	/* =====================================================================
	 * ADMIN: business screen
	 * ===================================================================== */
	public static function meta_boxes() {
		add_meta_box( 'bsg_listing_details', 'Business Details', array( __CLASS__, 'box_details' ), self::CPT, 'normal', 'high' );
		add_meta_box( 'bsg_listing_flags', 'Flags', array( __CLASS__, 'box_flags' ), self::CPT, 'side' );
		foreach ( apply_filters( 'bsg_destination_page_types', array( 'page' ) ) as $pt ) {
			add_meta_box( 'bsg_page_dest', 'BlueSpot Destination', array( __CLASS__, 'box_page_dest' ), $pt, 'side' );
		}
	}

	public static function box_details( $post ) {
		wp_nonce_field( 'bsg_listing', 'bsg_listing_nonce' );
		$f = array(
			'_bsg_subtype'   => array( 'Business type', 'e.g. Mexican Restaurant' ),
			'_bsg_phone'     => array( 'Phone', '(770) 555-0100' ),
			'_bsg_website'   => array( 'Website', 'https://' ),
			'_bsg_address'   => array( 'Street address', '123 Main St' ),
			'_bsg_city'      => array( 'City', 'Cartersville' ),
			'_bsg_state'     => array( 'State', 'GA' ),
			'_bsg_zip'       => array( 'ZIP', '30120' ),
			'_bsg_lat'       => array( 'Latitude', 'filled automatically' ),
			'_bsg_lng'       => array( 'Longitude', 'filled automatically' ),
			'_bsg_image_url' => array( 'Image URL (if no Featured image)', 'https://' ),
		);
		if ( get_post_meta( $post->ID, '_bsg_synced', true ) ) {
			echo '<p><strong>Synced from a guide map.</strong> Saving here marks it as edited, so future syncs won&rsquo;t unpublish it and never overwrite your changes.</p>';
		}
		echo '<p>Description = main editor (or Excerpt). Photo = <strong>Featured image</strong>. Set <strong>Categories</strong> and <strong>Search Tags</strong> on the right; tags are also added automatically. Latitude/longitude are looked up from the address if left empty.</p>';
		echo '<table class="form-table" role="presentation">';
		foreach ( $f as $key => $meta ) {
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s"></td></tr>',
				esc_attr( $key ), esc_html( $meta[0] ), esc_attr( get_post_meta( $post->ID, $key, true ) ), esc_attr( $meta[1] )
			);
		}
		$guides = array_filter( array_map( 'intval', get_post_meta( $post->ID, '_bsg_guide' ) ) );
		if ( $guides ) {
			echo '<tr><th>On guides</th><td>' . esc_html( implode( ', ', array_map( 'get_the_title', $guides ) ) ) . '</td></tr>';
		}
		echo '</table>';
	}

	public static function box_flags( $post ) {
		$flags = array(
			'_bsg_favorite'   => array( 'Local Favorite', 'Appears in Local Favorites with a badge.' ),
			'_bsg_featured'   => array( 'Featured', 'Ranked higher, with a Featured tag.' ),
			'_bsg_advertiser' => array( 'Advertiser (active)', 'Active advertiser. Ranked higher.' ),
		);
		foreach ( $flags as $key => $info ) {
			echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( get_post_meta( $post->ID, $key, true ), '1', false ) . '> <strong>' . esc_html( $info[0] ) . '</strong></label><br><span class="description">' . esc_html( $info[1] ) . '</span></p>';
		}
		echo '<p class="description">To hide a listing, switch it to Draft. Relevance always comes first, so a flag never pushes an unrelated business above a better match.</p>';
	}

	public static function save_listing( $post_id, $post ) {
		if ( ! isset( $_POST['bsg_listing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bsg_listing_nonce'] ) ), 'bsg_listing' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( '_bsg_subtype', '_bsg_phone', '_bsg_address', '_bsg_city', '_bsg_state', '_bsg_zip', '_bsg_lat', '_bsg_lng' ) as $k ) {
			update_post_meta( $post_id, $k, isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '' );
		}
		foreach ( array( '_bsg_website', '_bsg_image_url' ) as $k ) {
			update_post_meta( $post_id, $k, isset( $_POST[ $k ] ) ? esc_url_raw( wp_unslash( $_POST[ $k ] ) ) : '' );
		}
		foreach ( array( '_bsg_favorite', '_bsg_featured', '_bsg_advertiser' ) as $k ) {
			update_post_meta( $post_id, $k, empty( $_POST[ $k ] ) ? '0' : '1' );
		}
		update_post_meta( $post_id, '_bsg_manual', '1' );

		/* coordinates from the address when missing */
		if ( ! get_post_meta( $post_id, '_bsg_lat', true ) ) {
			$addr = trim( implode( ', ', array_filter( array(
				get_post_meta( $post_id, '_bsg_address', true ),
				get_post_meta( $post_id, '_bsg_city', true ),
				trim( get_post_meta( $post_id, '_bsg_state', true ) . ' ' . get_post_meta( $post_id, '_bsg_zip', true ) ),
			) ) ) );
			$geo  = $addr ? self::geocode( $addr ) : null;
			if ( $geo ) {
				update_post_meta( $post_id, '_bsg_lat', $geo[0] );
				update_post_meta( $post_id, '_bsg_lng', $geo[1] );
			}
		}
		self::auto_tag( $post_id, $post->post_title . ' ' . get_post_meta( $post_id, '_bsg_subtype', true ) . ' ' . $post->post_excerpt . ' ' . $post->post_content );

		$cats = wp_get_object_terms( $post_id, self::TAX_CAT, array( 'fields' => 'ids' ) );
		if ( empty( $cats ) || is_wp_error( $cats ) ) {
			$guess = self::guess_category( get_post_meta( $post_id, '_bsg_subtype', true ) . ' ' . $post->post_title );
			if ( $guess ) {
				wp_set_object_terms( $post_id, array( $guess ), self::TAX_CAT, false );
			}
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['bsg_where'] = 'Location';
				$new['bsg_flags'] = 'Flags';
			}
		}
		return $new;
	}

	public static function column_value( $col, $post_id ) {
		if ( 'bsg_where' === $col ) {
			$city = get_post_meta( $post_id, '_bsg_city', true );
			$st   = get_post_meta( $post_id, '_bsg_state', true );
			echo esc_html( trim( $city . ( $city && $st ? ', ' : '' ) . $st ) );
			if ( ! get_post_meta( $post_id, '_bsg_lat', true ) ) {
				echo '<br><span style="color:#b32d2e">No map location - add an address</span>';
			}
		} elseif ( 'bsg_flags' === $col ) {
			$out = array();
			foreach ( array( '_bsg_favorite' => 'Local Favorite', '_bsg_featured' => 'Featured', '_bsg_advertiser' => 'Advertiser', '_bsg_synced' => 'From map' ) as $k => $l ) {
				if ( get_post_meta( $post_id, $k, true ) ) {
					$out[] = $l;
				}
			}
			echo esc_html( implode( ', ', $out ) );
		}
	}

	/* =====================================================================
	 * ADMIN: destinations (optional; used for the state lists, guide links and service areas)
	 * ===================================================================== */
	private static function state_select( $current ) {
		$html = '<select name="bsg_state" id="bsg_state"><option value="">Choose a state</option>';
		foreach ( self::$states as $slug => $info ) {
			$html .= '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $info[0] ) . '</option>';
		}
		return $html . '</select>';
	}

	private static function guide_select( $current ) {
		$html = '<select name="bsg_guide" id="bsg_guide"><option value="">None</option>';
		foreach ( get_pages( array( 'sort_column' => 'post_title' ) ) as $p ) {
			$html .= '<option value="' . (int) $p->ID . '"' . selected( (int) $current, (int) $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		return $html . '</select>';
	}

	public static function dest_add_fields() {
		wp_nonce_field( 'bsg_dest', 'bsg_dest_nonce' );
		echo '<div class="form-field"><label for="bsg_state">State</label>' . self::state_select( '' ) . '<p>Name it by the city only, e.g. <em>Cartersville</em>.</p></div>'; // phpcs:ignore
		echo '<div class="form-field"><label for="bsg_guide">City guide page (optional)</label>' . self::guide_select( 0 ) . '</div>'; // phpcs:ignore
	}

	public static function dest_edit_fields( $term ) {
		wp_nonce_field( 'bsg_dest', 'bsg_dest_nonce' );
		$st = get_term_meta( $term->term_id, 'bsg_state', true );
		echo '<tr class="form-field"><th><label for="bsg_state">State</label></th><td>' . self::state_select( $st ) . '</td></tr>'; // phpcs:ignore
		echo '<tr class="form-field"><th><label for="bsg_guide">City guide page</label></th><td>' . self::guide_select( get_term_meta( $term->term_id, 'bsg_guide', true ) ) . '</td></tr>'; // phpcs:ignore
		if ( $st ) {
			$u = self::url( $st, sanitize_title( $term->name ) );
			echo '<tr><th>Results page</th><td><a href="' . esc_url( $u ) . '" target="_blank">' . esc_html( $u ) . '</a></td></tr>';
		}
	}

	public static function save_dest_fields( $term_id ) {
		if ( ! isset( $_POST['bsg_dest_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bsg_dest_nonce'] ) ), 'bsg_dest' ) ) {
			return;
		}
		$state = isset( $_POST['bsg_state'] ) ? sanitize_title( wp_unslash( $_POST['bsg_state'] ) ) : '';
		if ( isset( self::$states[ $state ] ) ) {
			update_term_meta( $term_id, 'bsg_state', $state );
			delete_term_meta( $term_id, 'bsg_lat' );
			delete_term_meta( $term_id, 'bsg_lng' );
			$term = get_term( $term_id, self::TAX_DEST );
			$want = sanitize_title( $term->name ) . '-' . self::abbr( $state );
			if ( $term && $term->slug !== $want ) {
				remove_action( 'edited_' . self::TAX_DEST, array( __CLASS__, 'save_dest_fields' ) );
				wp_update_term( $term_id, self::TAX_DEST, array( 'slug' => $want ) );
				add_action( 'edited_' . self::TAX_DEST, array( __CLASS__, 'save_dest_fields' ) );
			}
		}
		update_term_meta( $term_id, 'bsg_guide', isset( $_POST['bsg_guide'] ) ? (int) $_POST['bsg_guide'] : 0 );
	}

	public static function dest_columns( $cols ) {
		$cols['bsg_state'] = 'State';
		return $cols;
	}

	public static function dest_column_value( $content, $col, $term_id ) {
		if ( 'bsg_state' === $col ) {
			$s = get_term_meta( $term_id, 'bsg_state', true );
			return isset( self::$states[ $s ] ) ? esc_html( self::$states[ $s ][0] ) : '<em>Not set</em>';
		}
		return $content;
	}

	/* =====================================================================
	 * ADMIN: campground / guide pages -> destination (for iConnectTag links)
	 * ===================================================================== */
	public static function box_page_dest( $post ) {
		wp_nonce_field( 'bsg_page_dest', 'bsg_page_dest_nonce' );
		$cur   = (int) get_post_meta( $post->ID, '_bsg_dest', true );
		$terms = get_terms( array( 'taxonomy' => self::TAX_DEST, 'hide_empty' => false, 'orderby' => 'name' ) );
		echo '<p class="description">For Premier Property pages: an iConnectTag link with <code>?bsg_place=' . esc_html( $post->post_name ) . '</code> sets this destination.</p>';
		echo '<select name="_bsg_dest" style="width:100%"><option value="">None</option>';
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$st = get_term_meta( $t->term_id, 'bsg_state', true );
				echo '<option value="' . (int) $t->term_id . '"' . selected( $cur, (int) $t->term_id, false ) . '>' . esc_html( $t->name . ( isset( self::$states[ $st ] ) ? ', ' . self::$states[ $st ][0] : '' ) ) . '</option>';
			}
		}
		echo '</select>';
	}

	public static function save_page_destination( $post_id, $post ) {
		if ( ! isset( $_POST['bsg_page_dest_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bsg_page_dest_nonce'] ) ), 'bsg_page_dest' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_bsg_dest', isset( $_POST['_bsg_dest'] ) ? (int) $_POST['_bsg_dest'] : 0 );
	}
}

BSG_Local_Guide::init();
register_activation_hook( __FILE__, array( 'BSG_Local_Guide', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BSG_Local_Guide', 'deactivate' ) );
