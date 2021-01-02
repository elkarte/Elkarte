<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Used to add emoji images to text
 *
 * What it does:
 *
 * - Searches text for :tag: strings
 * - If tag is found to be a known emoji, replaces it with an image tag
 */
class Emoji
{
	// Array of keys with known emoji names
	private $shortcode_replace = array(
		'joy' => '1f602', 'heart' => '2764', 'heart_eyes' => '1f60d', 'sob' => '1f62d', 'blush' => '1f60a', 'unamused' => '1f612', 'kissing_heart' => '1f618', 'two_hearts' => '1f495', 'weary' => '1f629', 'ok_hand' => '1f44c',
		'pensive' => '1f614', 'smirk' => '1f60f', 'grin' => '1f601', 'recycle' => '267b', 'wink' => '1f609', 'thumbsup' => '1f44d', 'pray' => '1f64f', 'relieved' => '1f60c', 'notes' => '1f3b6', 'flushed' => '1f633',
		'raised_hands' => '1f64c', 'see_no_evil' => '1f648', 'cry' => '1f622', 'sunglasses' => '1f60e', 'v' => '270c', 'eyes' => '1f440', 'sweat_smile' => '1f605', 'sparkles' => '2728', 'sleeping' => '1f634', 'smile' => '1f604',
		'purple_heart' => '1f49c', 'broken_heart' => '1f494', 'hundred_points' => '1f4af', 'expressionless' => '1f611', 'sparkling_heart' => '1f496', 'blue_heart' => '1f499', 'confused' => '1f615', 'information_desk_person' => '1f481', 'stuck_out_tongue_winking_eye' => '1f61c', 'disappointed' => '1f61e',
		'yum' => '1f60b', 'neutral_face' => '1f610', 'sleepy' => '1f62a', 'clap' => '1f44f', 'cupid' => '1f498', 'heartpulse' => '1f497', 'revolving_hearts' => '1f49e', 'arrow_left' => '2b05', 'speak_no_evil' => '1f64a', 'raised_hand' => '270b',
		'kiss' => '1f48b', 'point_right' => '1f449', 'cherry_blossom' => '1f338', 'scream' => '1f631', 'fire' => '1f525', 'rage' => '1f621', 'smiley' => '1f603', 'tada' => '1f389', 'oncoming_fist' => '1f44a', 'tired_face' => '1f62b',
		'camera' => '1f4f7', 'rose' => '1f339', 'stuck_out_tongue_closed_eyes' => '1f61d', 'muscle' => '1f4aa', 'skull' => '1f480', 'sunny' => '2600', 'yellow_heart' => '1f49b', 'triumph' => '1f624', 'new_moon_with_face' => '1f31a', 'laughing' => '1f606',
		'sweat' => '1f613', 'point_left' => '1f448', 'heavy_check_mark' => '2714', 'heart_eyes_cat' => '1f63b', 'grinning' => '1f600', 'mask' => '1f637', 'green_heart' => '1f49a', 'wave' => '1f44b', 'persevere' => '1f623', 'heartbeat' => '1f493',
		'arrow_forward' => '25b6', 'arrow_backward' => '25c0', 'arrow_right_hook' => '21aa', 'leftwards_arrow_with_hook' => '21a9', 'crown' => '1f451', 'kissing_closed_eyes' => '1f61a', 'stuck_out_tongue' => '1f61b', 'disappointed_relieved' => '1f625', 'innocent' => '1f607', 'headphones' => '1f3a7',
		'white_check_mark' => '2705', 'confounded' => '1f616', 'arrow_right' => '27a1', 'angry' => '1f620', 'grimacing' => '1f62c', 'star2' => '1f31f', 'gun' => '1f52b', 'raising_hand' => '1f64b', 'thumbsdown' => '1f44e', 'dancer' => '1f483',
		'musical_note' => '1f3b5', 'no_mouth' => '1f636', 'dizzy' => '1f4ab', 'fist' => '270a', 'point_down' => '1f447', 'red_circle' => '1f534', 'no_good' => '1f645', 'boom' => '1f4a5', 'thought_balloon' => '1f4ad', 'tongue' => '1f445',
		'poop' => '1f4a9', 'cold_sweat' => '1f630', 'gem' => '1f48e', 'ok_woman' => '1f646', 'pizza' => '1f355', 'joy_cat' => '1f639', 'sun_with_face' => '1f31e', 'leaves' => '1f343', 'sweat_drops' => '1f4a6', 'penguin' => '1f427',
		'zzz' => '1f4a4', 'walking' => '1f6b6', 'airplane' => '2708', 'balloon' => '1f388', 'star' => '2b50', 'ribbon' => '1f380', 'ballot_box_with_check' => '2611', 'worried' => '1f61f', 'underage' => '1f51e', 'fearful' => '1f628',
		'four_leaf_clover' => '1f340', 'hibiscus' => '1f33a', 'microphone' => '1f3a4', 'open_hands' => '1f450', 'ghost' => '1f47b', 'palm_tree' => '1f334', 'bangbang' => '203c', 'nail_care' => '1f485', 'x' => '274c', 'alien' => '1f47d',
		'bow' => '1f647', 'cloud' => '2601', 'soccer' => '26bd', 'angel' => '1f47c', 'dancers' => '1f46f', 'exclamation' => '2757', 'snowflake' => '2744', 'point_up' => '261d', 'kissing_smiling_eyes' => '1f619', 'rainbow' => '1f308',
		'crescent_moon' => '1f319', 'heart_decoration' => '1f49f', 'gift_heart' => '1f49d', 'gift' => '1f381', 'beers' => '1f37b', 'anguished' => '1f627', 'earth_africa' => '1f30d', 'movie_camera' => '1f3a5', 'anchor' => '2693', 'zap' => '26a1',
		'heavy_multiplication_x' => '2716', 'runner' => '1f3c3', 'sunflower' => '1f33b', 'earth_americas' => '1f30e', 'bouquet' => '1f490', 'dog' => '1f436', 'moneybag' => '1f4b0', 'herb' => '1f33f', 'couple' => '1f46b', 'fallen_leaf' => '1f342',
		'tulip' => '1f337', 'birthday' => '1f382', 'cat' => '1f431', 'coffee' => '2615', 'dizzy_face' => '1f635', 'point_up_2' => '1f446', 'open_mouth' => '1f62e', 'hushed' => '1f62f', 'basketball' => '1f3c0', 'christmas_tree' => '1f384',
		'ring' => '1f48d', 'full_moon_with_face' => '1f31d', 'astonished' => '1f632', 'two_women_holding_hands' => '1f46d', 'money_with_wings' => '1f4b8', 'crying_cat_face' => '1f63f', 'hear_no_evil' => '1f649', 'dash' => '1f4a8', 'cactus' => '1f335', 'hotsprings' => '2668',
		'telephone' => '260e', 'maple_leaf' => '1f341', 'princess' => '1f478', 'massage' => '1f486', 'love_letter' => '1f48c', 'trophy' => '1f3c6', 'person_frowning' => '1f64d', 'us' => '1f1fa', 'confetti_ball' => '1f38a', 'blossom' => '1f33c',
		'kitchen_knife' => '1f52a', 'lips' => '1f444', 'fries' => '1f35f', 'doughnut' => '1f369', 'frowning' => '1f626', 'ocean' => '1f30a', 'bomb' => '1f4a3', 'ok' => '1f197', 'cyclone' => '1f300', 'rocket' => '1f680',
		'umbrella' => '2614', 'couplekiss' => '1f48f', 'couple_with_heart' => '1f491', 'lollipop' => '1f36d', 'clapper' => '1f3ac', 'pig' => '1f437', 'smiling_imp' => '1f608', 'imp' => '1f47f', 'bee' => '1f41d', 'kissing_cat' => '1f63d',
		'anger' => '1f4a2', 'musical_score' => '1f3bc', 'santa' => '1f385', 'earth_asia' => '1f30f', 'football' => '1f3c8', 'guitar' => '1f3b8', 'panda_face' => '1f43c', 'speech_balloon' => '1f4ac', 'strawberry' => '1f353', 'smirk_cat' => '1f63c',
		'banana' => '1f34c', 'watermelon' => '1f349', 'snowman' => '26c4', 'smile_cat' => '1f638', 'top' => '1f51d', 'eggplant' => '1f346', 'crystal_ball' => '1f52e', 'fork_and_knife' => '1f374', 'calling' => '1f4f2', 'iphone' => '1f4f1',
		'partly_sunny' => '26c5', 'warning' => '26a0', 'scream_cat' => '1f640', 'small_orange_diamond' => '1f538', 'baby' => '1f476', 'feet' => '1f43e', 'footprints' => '1f463', 'beer' => '1f37a', 'wine_glass' => '1f377', 'o' => '2b55',
		'video_camera' => '1f4f9', 'rabbit' => '1f430', 'tropical_drink' => '1f379', 'smoking' => '1f6ac', 'space_invader' => '1f47e', 'peach' => '1f351', 'snake' => '1f40d', 'turtle' => '1f422', 'cherries' => '1f352', 'kissing' => '1f617',
		'frog' => '1f438', 'milky_way' => '1f30c', 'rotating_light' => '1f6a8', 'hatching_chick' => '1f423', 'closed_book' => '1f4d5', 'candy' => '1f36c', 'hamburger' => '1f354', 'bear' => '1f43b', 'tiger' => '1f42f', 'automobile' => '1f697',
		'icecream' => '1f366', 'pineapple' => '1f34d', 'ear_of_rice' => '1f33e', 'syringe' => '1f489', 'put_litter_in_its_place' => '1f6ae', 'chocolate_bar' => '1f36b', 'black_small_square' => '25aa', 'tv' => '1f4fa', 'pill' => '1f48a', 'octopus' => '1f419',
		'jack_o_lantern' => '1f383', 'grapes' => '1f347', 'smiley_cat' => '1f63a', 'cd' => '1f4bf', 'cocktail' => '1f378', 'cake' => '1f370', 'video_game' => '1f3ae', 'arrow_down' => '2b07', 'no_entry_sign' => '1f6ab', 'lipstick' => '1f484',
		'whale' => '1f433', 'memo' => '1f4dd', 'cookie' => '1f36a', 'dolphin' => '1f42c', 'loud_sound' => '1f50a', 'man' => '1f468', 'hatched_chick' => '1f425', 'monkey' => '1f412', 'books' => '1f4da', 'japanese_ogre' => '1f479',
		'guardsman' => '1f482', 'loudspeaker' => '1f4e2', 'scissors' => '2702', 'girl' => '1f467', 'mortar_board' => '1f393', 'fr' => '1f1eb', 'baseball' => '26be', 'vertical_traffic_light' => '1f6a6', 'woman' => '1f469', 'fireworks' => '1f386',
		'stars' => '1f320', 'sos' => '1f198', 'mushroom' => '1f344', 'pouting_cat' => '1f63e', 'left_luggage' => '1f6c5', 'high_heel' => '1f460', 'dart' => '1f3af', 'swimmer' => '1f3ca', 'key' => '1f511', 'bikini' => '1f459',
		'family' => '1f46a', 'pencil2' => '270f', 'elephant' => '1f418', 'droplet' => '1f4a7', 'seedling' => '1f331', 'apple' => '1f34e', 'cool' => '1f192', 'telephone_receiver' => '1f4de', 'dollar' => '1f4b5', 'house_with_garden' => '1f3e1',
		'book' => '1f4d6', 'haircut' => '1f487', 'computer' => '1f4bb', 'bulb' => '1f4a1', 'question' => '2753', 'back' => '1f519', 'boy' => '1f466', 'closed_lock_with_key' => '1f510', 'person_with_pouting_face' => '1f64e', 'tangerine' => '1f34a',
		'sunrise' => '1f305', 'poultry_leg' => '1f357', 'blue_circle' => '1f535', 'oncoming_automobile' => '1f698', 'shaved_ice' => '1f367', 'bird' => '1f426', 'first_quarter_moon_with_face' => '1f31b', 'eyeglasses' => '1f453', 'goat' => '1f410', 'night_with_stars' => '1f303',
		'older_woman' => '1f475', 'black_circle' => '26ab', 'new_moon' => '1f311', 'two_men_holding_hands' => '1f46c', 'white_circle' => '26aa', 'customs' => '1f6c3', 'tropical_fish' => '1f420', 'house' => '1f3e0', 'arrows_clockwise' => '1f503', 'last_quarter_moon_with_face' => '1f31c',
		'round_pushpin' => '1f4cd', 'full_moon' => '1f315', 'athletic_shoe' => '1f45f', 'lemon' => '1f34b', 'baby_bottle' => '1f37c', 'artist_palette' => '1f3a8', 'spaghetti' => '1f35d', 'wind_chime' => '1f390', 'fish_cake' => '1f365', 'evergreen_tree' => '1f332',
		'up' => '1f199', 'arrow_up' => '2b06', 'arrow_upper_right' => '2197', 'arrow_lower_right' => '2198', 'arrow_lower_left' => '2199', 'performing_arts' => '1f3ad', 'nose' => '1f443', 'pig_nose' => '1f43d', 'fish' => '1f41f', 'man_with_turban' => '1f473',
		'koala' => '1f428', 'ear' => '1f442', 'eight_spoked_asterisk' => '2733', 'small_blue_diamond' => '1f539', 'shower' => '1f6bf', 'bug' => '1f41b', 'ramen' => '1f35c', 'tophat' => '1f3a9', 'bride_with_veil' => '1f470', 'fuelpump' => '26fd',
		'checkered_flag' => '1f3c1', 'horse' => '1f434', 'watch' => '231a', 'monkey_face' => '1f435', 'baby_symbol' => '1f6bc', 'new' => '1f195', 'free' => '1f193', 'sparkler' => '1f387', 'corn' => '1f33d', 'tennis' => '1f3be',
		'alarm_clock' => '23f0', 'battery' => '1f50b', 'grey_exclamation' => '2755', 'wolf' => '1f43a', 'moyai' => '1f5ff', 'cow' => '1f42e', 'mega' => '1f4e3', 'older_man' => '1f474', 'dress' => '1f457', 'link' => '1f517',
		'chicken' => '1f414', 'cooking' => '1f373', 'whale2' => '1f40b', 'arrow_upper_left' => '2196', 'deciduous_tree' => '1f333', 'bento' => '1f371', 'pushpin' => '1f4cc', 'soon' => '1f51c', 'repeat' => '1f501', 'dragon' => '1f409',
		'hamster' => '1f439', 'golf' => '26f3', 'surfer' => '1f3c4', 'mouse' => '1f42d', 'waxing_crescent_moon' => '1f312', 'blue_car' => '1f699', 'a' => '1f170', 'interrobang' => '2049', 'u5272' => '1f239', 'electric_plug' => '1f50c',
		'first_quarter_moon' => '1f313', 'cancer' => '264b', 'trident' => '1f531', 'bread' => '1f35e', 'cop' => '1f46e', 'tea' => '1f375', 'fishing_pole_and_fish' => '1f3a3', 'waxing_gibbous_moon' => '1f314', 'bike' => '1f6b2', 'bust_in_silhouette' => '1f464',
		'rice' => '1f35a', 'radio' => '1f4fb', 'baby_chick' => '1f424', 'arrow_heading_down' => '2935', 'waning_crescent_moon' => '1f318', 'arrow_up_down' => '2195', 'last_quarter_moon' => '1f317', 'radio_button' => '1f518', 'sheep' => '1f411', 'person_with_blond_hair' => '1f471',
		'waning_gibbous_moon' => '1f316', 'lock' => '1f512', 'green_apple' => '1f34f', 'japanese_goblin' => '1f47a', 'curly_loop' => '27b0', 'triangular_flag_on_post' => '1f6a9', 'arrows_counterclockwise' => '1f504', 'racehorse' => '1f40e', 'fried_shrimp' => '1f364', 'sunrise_over_mountains' => '1f304',
		'volcano' => '1f30b', 'rooster' => '1f413', 'inbox_tray' => '1f4e5', 'wedding' => '1f492', 'sushi' => '1f363', 'wavy_dash' => '3030', 'ice_cream' => '1f368', 'rewind' => '23ea', 'tomato' => '1f345', 'rabbit2' => '1f407',
		'eight_pointed_black_star' => '2734', 'small_red_triangle' => '1f53a', 'high_brightness' => '1f506', 'heavy_plus_sign' => '2795', 'man_with_gua_pi_mao' => '1f472', 'convenience_store' => '1f3ea', 'busts_in_silhouette' => '1f465', 'beetle' => '1f41e', 'small_red_triangle_down' => '1f53b', 'arrow_heading_up' => '2934',
		'name_badge' => '1f4db', 'bath' => '1f6c0', 'no_entry' => '26d4', 'crocodile' => '1f40a', 'chestnut' => '1f330', 'dog2' => '1f415', 'cat2' => '1f408', 'hammer' => '1f528', 'meat_on_bone' => '1f356', 'shell' => '1f41a',
		'sparkle' => '2747', 'sailboat' => '26f5', 'b' => '1f171', 'm' => '24c2', 'poodle' => '1f429', 'aquarius' => '2652', 'stew' => '1f372', 'jeans' => '1f456', 'honey_pot' => '1f36f', 'musical_keyboard' => '1f3b9',
		'unlock' => '1f513', 'black_nib' => '2712', 'statue_of_liberty' => '1f5fd', 'heavy_dollar_sign' => '1f4b2', 'snowboarder' => '1f3c2', 'white_flower' => '1f4ae', 'necktie' => '1f454', 'diamond_shape_with_a_dot_inside' => '1f4a0', 'aries' => '2648', 'womens' => '1f6ba',
		'ant' => '1f41c', 'scorpius' => '264f', 'city_sunset' => '1f307', 'hourglass_flowing_sand' => '23f3', 'o2' => '1f17e', 'dragon_face' => '1f432', 'snail' => '1f40c', 'dvd' => '1f4c0', 'shirt' => '1f455', 'game_die' => '1f3b2',
		'heavy_minus_sign' => '2796', 'dolls' => '1f38e', 'sagittarius' => '2650', '8ball' => '1f3b1', 'bus' => '1f68c', 'custard' => '1f36e', 'crossed_flags' => '1f38c', 'part_alternation_mark' => '303d', 'camel' => '1f42b', 'curry' => '1f35b',
		'steam_locomotive' => '1f682', 'hospital' => '1f3e5', 'large_blue_diamond' => '1f537', 'tanabata_tree' => '1f38b', 'bell' => '1f514', 'leo' => '264c', 'gemini' => '264a', 'pear' => '1f350', 'large_orange_diamond' => '1f536', 'taurus' => '2649',
		'globe_with_meridians' => '1f310', 'door' => '1f6aa', 'clock6' => '1f555', 'oncoming_police_car' => '1f694', 'envelope_with_arrow' => '1f4e9', 'closed_umbrella' => '1f302', 'saxophone' => '1f3b7', 'church' => '26ea', 'bicyclist' => '1f6b4', 'pisces' => '2653',
		'dango' => '1f361', 'capricorn' => '2651', 'office' => '1f3e2', 'rowboat' => '1f6a3', 'womans_hat' => '1f452', 'mans_shoe' => '1f45e', 'love_hotel' => '1f3e9', 'mount_fuji' => '1f5fb', 'dromedary_camel' => '1f42a', 'handbag' => '1f45c',
		'hourglass' => '231b', 'negative_squared_cross_mark' => '274e', 'trumpet' => '1f3ba', 'school' => '1f3eb', 'cow2' => '1f404', 'cityscape_at_dusk' => '1f306', 'construction_worker' => '1f477', 'toilet' => '1f6bd', 'pig2' => '1f416', 'grey_question' => '2754',
		'beginner' => '1f530', 'violin' => '1f3bb', 'on' => '1f51b', 'credit_card' => '1f4b3', 'id' => '1f194', 'secret' => '3299', 'ferris_wheel' => '1f3a1', 'bowling' => '1f3b3', 'libra' => '264e', 'virgo' => '264d',
		'barber' => '1f488', 'purse' => '1f45b', 'roller_coaster' => '1f3a2', 'rat' => '1f400', 'date' => '1f4c5', 'rugby_football' => '1f3c9', 'ram' => '1f40f', 'arrow_up_small' => '1f53c', 'black_square_button' => '1f532', 'mobile_phone_off' => '1f4f4',
		'tokyo_tower' => '1f5fc', 'congratulations' => '3297', 'kimono' => '1f458', 'ship' => '1f6a2', 'mag_right' => '1f50e', 'mag' => '1f50d', 'fire_engine' => '1f692', 'clock1130' => '1f566', 'police_car' => '1f693', 'black_joker' => '1f0cf',
		'bridge_at_night' => '1f309', 'package' => '1f4e6', 'oncoming_taxi' => '1f696', 'calendar' => '1f4c6', 'horse_racing' => '1f3c7', 'tiger2' => '1f405', 'boot' => '1f462', 'ambulance' => '1f691', 'white_square_button' => '1f533', 'boar' => '1f417',
		'school_satchel' => '1f392', 'loop' => '27bf', 'pound' => '1f4b7', 'information_source' => '2139', 'ox' => '1f402', 'rice_ball' => '1f359', 'vs' => '1f19a', 'end' => '1f51a', 'parking' => '1f17f', 'sandal' => '1f461',
		'tent' => '26fa', 'seat' => '1f4ba', 'taxi' => '1f695', 'black_medium_small_square' => '25fe', 'briefcase' => '1f4bc', 'newspaper' => '1f4f0', 'circus_tent' => '1f3aa', 'six_pointed_star' => '1f52f', 'mens' => '1f6b9', 'european_castle' => '1f3f0',
		'flashlight' => '1f526', 'foggy' => '1f301', 'arrow_double_up' => '23eb', 'bamboo' => '1f38d', 'ticket' => '1f3ab', 'helicopter' => '1f681', 'minidisc' => '1f4bd', 'oncoming_bus' => '1f68d', 'melon' => '1f348', 'white_small_square' => '25ab',
		'european_post_office' => '1f3e4', 'keycap_ten' => '1f51f', 'notebook' => '1f4d3', 'no_bell' => '1f515', 'oden' => '1f362', 'flags' => '1f38f', 'carousel_horse' => '1f3a0', 'blowfish' => '1f421', 'chart_with_upwards_trend' => '1f4c8', 'sweet_potato' => '1f360',
		'ski' => '1f3bf', 'clock12' => '1f55b', 'signal_strength' => '1f4f6', 'construction' => '1f6a7', 'black_medium_square' => '25fc', 'satellite' => '1f4e1', 'euro' => '1f4b6', 'womans_clothes' => '1f45a', 'ledger' => '1f4d2', 'leopard' => '1f406',
		'low_brightness' => '1f505', 'clock3' => '1f552', 'department_store' => '1f3ec', 'truck' => '1f69a', 'sake' => '1f376', 'railway_car' => '1f683', 'speedboat' => '1f6a4', 'vhs' => '1f4fc', 'clock1' => '1f550', 'arrow_double_down' => '23ec',
		'water_buffalo' => '1f403', 'arrow_down_small' => '1f53d', 'yen' => '1f4b4', 'mute' => '1f507', 'running_shirt_with_sash' => '1f3bd', 'white_large_square' => '2b1c', 'wheelchair' => '267f', 'clock2' => '1f551', 'paperclip' => '1f4ce', 'atm' => '1f3e7',
		'cinema' => '1f3a6', 'telescope' => '1f52d', 'rice_scene' => '1f391', 'blue_book' => '1f4d8', 'white_medium_square' => '25fb', 'postbox' => '1f4ee', 'e-mail' => '1f4e7', 'mouse2' => '1f401', 'bullettrain_side' => '1f684', 'ideograph_advantage' => '1f250',
		'nut_and_bolt' => '1f529', 'ng' => '1f196', 'hotel' => '1f3e8', 'wc' => '1f6be', 'izakaya_lantern' => '1f3ee', 'repeat_one' => '1f502', 'mailbox_with_mail' => '1f4ec', 'chart_with_downwards_trend' => '1f4c9', 'green_book' => '1f4d7', 'tractor' => '1f69c',
		'fountain' => '26f2', 'metro' => '1f687', 'clipboard' => '1f4cb', 'no_mobile_phones' => '1f4f5', 'clock4' => '1f553', 'no_smoking' => '1f6ad', 'black_large_square' => '2b1b', 'slot_machine' => '1f3b0', 'clock5' => '1f554', 'bathtub' => '1f6c1',
		'scroll' => '1f4dc', 'station' => '1f689', 'rice_cracker' => '1f358', 'bank' => '1f3e6', 'wrench' => '1f527', 'u6307' => '1f22f', 'articulated_lorry' => '1f69b', 'page_facing_up' => '1f4c4', 'ophiuchus' => '26ce', 'bar_chart' => '1f4ca',
		'no_pedestrians' => '1f6b7', 'vibration_mode' => '1f4f3', 'clock10' => '1f559', 'clock9' => '1f558', 'bullettrain_front' => '1f685', 'minibus' => '1f690', 'tram' => '1f68a', 'clock8' => '1f557', 'u7a7a' => '1f233', 'traffic_light' => '1f6a5',
		'mountain_bicyclist' => '1f6b5', 'microscope' => '1f52c', 'japanese_castle' => '1f3ef', 'bookmark' => '1f516', 'bookmark_tabs' => '1f4d1', 'pouch' => '1f45d', 'ab' => '1f18e', 'page_with_curl' => '1f4c3', 'flower_playing_cards' => '1f3b4', 'clock11' => '1f55a',
		'fax' => '1f4e0', 'clock7' => '1f556', 'white_medium_small_square' => '25fd', 'currency_exchange' => '1f4b1', 'sound' => '1f509', 'chart' => '1f4b9', 'cl' => '1f191', 'floppy_disk' => '1f4be', 'post_office' => '1f3e3', 'speaker' => '1f508',
		'japan' => '1f5fe', 'u55b6' => '1f23a', 'mahjong' => '1f004', 'incoming_envelope' => '1f4e8', 'orange_book' => '1f4d9', 'restroom' => '1f6bb', 'u7121' => '1f21a', 'u6709' => '1f236', 'triangular_ruler' => '1f4d0', 'train' => '1f68b',
		'u7533' => '1f238', 'trolleybus' => '1f68e', 'u6708' => '1f237', 'input_numbers' => '1f522', 'notebook_with_decorative_cover' => '1f4d4', 'u7981' => '1f232', 'u6e80' => '1f235', 'postal_horn' => '1f4ef', 'factory' => '1f3ed', 'children_crossing' => '1f6b8',
		'train2' => '1f686', 'straight_ruler' => '1f4cf', 'pager' => '1f4df', 'accept' => '1f251', 'u5408' => '1f234', 'lock_with_ink_pen' => '1f50f', 'clock130' => '1f55c', 'sa' => '1f202', 'outbox_tray' => '1f4e4', 'twisted_rightwards_arrows' => '1f500',
		'mailbox' => '1f4eb', 'light_rail' => '1f688', 'clock930' => '1f564', 'busstop' => '1f68f', 'open_file_folder' => '1f4c2', 'file_folder' => '1f4c1', 'potable_water' => '1f6b0', 'card_index' => '1f4c7', 'clock230' => '1f55d', 'monorail' => '1f69d',
		'clock1230' => '1f567', 'clock1030' => '1f565', 'abc' => '1f524', 'mailbox_closed' => '1f4ea', 'clock430' => '1f55f', 'mountain_railway' => '1f69e', 'do_not_litter' => '1f6af', 'clock330' => '1f55e', 'heavy_division_sign' => '2797', 'clock730' => '1f562',
		'clock530' => '1f560', 'capital_abcd' => '1f520', 'mailbox_with_no_mail' => '1f4ed', 'symbols' => '1f523', 'aerial_tramway' => '1f6a1', 'clock830' => '1f563', 'clock630' => '1f561', 'abcd' => '1f521', 'mountain_cableway' => '1f6a0', 'koko' => '1f201',
		'passport_control' => '1f6c2', 'non-potable_water' => '1f6b1', 'suspension_railway' => '1f69f', 'baggage_claim' => '1f6c4', 'no_bicycles' => '1f6b3', 'detective' => '1f575', 'frowning_face' => '2639', 'skull_crossbones' => '2620', 'hugging' => '1f917', 'robot' => '1f916',
		'face_with_headbandage' => '1f915', 'thinking' => '1f914', 'nerd' => '1f913', 'face_with_thermometer' => '1f912', 'moneymouth_face' => '1f911', 'zipper_mouth' => '1f910', 'rolling_eyes' => '1f644', 'upside_down' => '1f643', 'slight_smile' => '1f642', 'slightly_frowning_face' => '1f641',
		'sign_of_the_horns' => '1f918', 'vulcan_salute' => '1f596', 'middle_finger' => '1f595', 'hand_with_fingers_splayed' => '1f590', 'writing_hand' => '270d', 'dark_sunglasses' => '1f576', 'eye' => '1f441', 'weightlifter' => '1f3cb', 'basketballer' => '26f9', 'man_in_suit' => '1f574',
		'golfer' => '1f3cc', 'heart_exclamation' => '2763', 'star_of_david' => '2721', 'cross' => '271d', 'fleur-de-lis' => '269c', 'atom' => '269b', 'wheel_of_dharma' => '2638', 'yin_yang' => '262f', 'peace' => '262e', 'star_and_crescent' => '262a',
		'orthodox_cross' => '2626', 'biohazard' => '2623', 'radioactive' => '2622', 'place_of_worship' => '1f6d0', 'anger_right' => '1f5ef', 'menorah' => '1f54e', 'om_symbol' => '1f549', 'funeral_urn' => '26b1', 'coffin' => '26b0', 'gear' => '2699',
		'alembic' => '2697', 'scales' => '2696', 'crossed_swords' => '2694', 'keyboard' => '2328', 'oil_drum' => '1f6e2', 'shield' => '1f6e1', 'hammer_and_wrench' => '1f6e0', 'bed' => '1f6cf', 'bellhop_bell' => '1f6ce', 'shopping_bags' => '1f6cd',
		'sleeping_accommodation' => '1f6cc', 'couch_and_lamp' => '1f6cb', 'ballot_box' => '1f5f3', 'dagger' => '1f5e1', 'rolledup_newspaper' => '1f5de', 'old_key' => '1f5dd', 'compression' => '1f5dc', 'spiral_calendar' => '1f5d3', 'spiral_notepad' => '1f5d2', 'wastebasket' => '1f5d1',
		'file_cabinet' => '1f5c4', 'card_file_box' => '1f5c3', 'card_index_dividers' => '1f5c2', 'framed_picture' => '1f5bc', 'trackball' => '1f5b2', 'computer_mouse' => '1f5b1', 'printer' => '1f5a8', 'desktop_computer' => '1f5a5', 'crayon' => '1f58d', 'paintbrush' => '1f58c',
		'fountain_pen' => '1f58b', 'pen' => '1f58a', 'linked_paperclips' => '1f587', 'joystick' => '1f579', 'hole' => '1f573', 'mantelpiece_clock' => '1f570', 'candle' => '1f56f', 'prayer_beads' => '1f4ff', 'film_projector' => '1f4fd', 'camera_with_flash' => '1f4f8',
		'amphora' => '1f3fa', 'label' => '1f3f7', 'flag_black' => '1f3f4', 'flag_white' => '1f3f3', 'film_frames' => '1f39e', 'control_knobs' => '1f39b', 'level_slider' => '1f39a', 'studio_microphone' => '1f399', 'thermometer' => '1f321', 'passenger_ship' => '1f6f3',
		'satellite' => '1f6f0', 'airplane_arriving' => '1f6ec', 'airplane_departure' => '1f6eb', 'small_airplane' => '1f6e9', 'motor_boat' => '1f6e5', 'railway_track' => '1f6e4', 'motorway' => '1f6e3', 'world_map' => '1f5fa', 'synagogue' => '1f54d', 'mosque' => '1f54c',
		'kaaba' => '1f54b', 'stadium' => '1f3df', 'national_park' => '1f3de', 'desert_island' => '1f3dd', 'desert' => '1f3dc', 'classical_building' => '1f3db', 'derelict_house' => '1f3da', 'cityscape' => '1f3d9', 'houses' => '1f3d8', 'building_construction' => '1f3d7',
		'beach_with_umbrella' => '1f3d6', 'camping' => '1f3d5', 'snowcapped_mountain' => '1f3d4', 'racing_car' => '1f3ce', 'motorcycle' => '1f3cd', 'bow_and_arrow' => '1f3f9', 'badminton' => '1f3f8', 'rosette' => '1f3f5', 'ping_pong' => '1f3d3', 'ice_hockey' => '1f3d2',
		'field_hockey' => '1f3d1', 'volleyball' => '1f3d0', 'cricket_game' => '1f3cf', 'medal' => '1f3c5', 'admission_tickets' => '1f39f', 'reminder_ribbon' => '1f397', 'military_medal' => '1f396', 'cheese_wedge' => '1f9c0', 'popcorn' => '1f37f', 'champagne' => '1f37e',
		'fork_and_knife_with_plate' => '1f37d', 'hot_pepper' => '1f336', 'burrito' => '1f32f', 'taco' => '1f32e', 'hotdog' => '1f32d', 'shamrock' => '2618', 'comet' => '2604', 'unicorn' => '1f984', 'turkey' => '1f983', 'scorpion' => '1f982',
		'lion_face' => '1f981', 'crab' => '1f980', 'spider_web' => '1f578', 'spider' => '1f577', 'dove' => '1f54a', 'chipmunk' => '1f43f', 'wind_blowing_face' => '1f32c', 'fog' => '1f32b', 'tornado' => '1f32a', 'cloud_with_lightning' => '1f329',
		'cloud_with_snow' => '1f328', 'cloud_with_rain' => '1f327', 'sun_behind_rain_cloud' => '1f326', 'sun_behind_large_cloud' => '1f325', 'sun_behind_small_cloud' => '1f324', 'speaking_head' => '1f5e3', 'record_button' => '23fa', 'stop_button' => '23f9', 'pause_button' => '23f8', 'play_pause' => '23ef',
		'track_previous' => '23ee', 'track_next' => '23ed', 'beach_umbrella' => '26f1', 'chains' => '26d3', 'pick' => '26cf', 'hammer_and_pick' => '2692', 'timer_clock' => '23f2', 'stopwatch' => '23f1', 'ferry' => '26f4', 'mountain' => '26f0',
		'ice_skate' => '26f8', 'skier' => '26f7', 'cloud_with_lightning_and_rain' => '26c8', 'rescue_worker’s_helmet' => '26d1', 'black_heart' => '1f5a4', 'speech_left' => '1f5e8', 'egg' => '1f95a', 'octagonal_sign' => '1f6d1', 'spades' => '2660', 'hearts' => '2665',
		'diamonds' => '2666', 'clubs' => '2663', 'drum' => '1f941', 'left_right_arrow' => '2194', 'copyright' => '00a9', 'registered' => '00ae', 'tm' => '2122', 'zero' => '0030', 'one' => '0031', 'two' => '0032',
		'three' => '0033', 'four' => '0034', 'five' => '0035', 'six' => '0036', 'seven' => '0037', 'eight' => '0038', 'nine' => '0039', 'rolling_on_the_floor_laughing' => '1f923', 'smiling_face' => '263a', 'lying_face' => '1f925',
		'drooling_face' => '1f924', 'nauseated_face' => '1f922', 'sneezing_face' => '1f927', 'cowboy_hat_face' => '1f920', 'clown_face' => '1f921', 'raised_back_of_hand' => '1f91a', 'crossed_fingers' => '1f91e', 'call_me_hand' => '1f919', 'leftfacing_fist' => '1f91b', 'rightfacing_fist' => '1f91c',
		'handshake' => '1f91d', 'selfie' => '1f933', 'person_facepalming' => '1f926', 'person_shrugging' => '1f937', 'prince' => '1f934', 'man_in_tuxedo' => '1f935', 'pregnant_woman' => '1f930', 'Mrs_Claus' => '1f936', 'man_dancing' => '1f57a', 'person_fencing' => '1f93a',
		'person_cartwheeling' => '1f938', 'people_wrestling' => '1f93c', 'person_playing_water_polo' => '1f93d', 'person_playing_handball' => '1f93e', 'person_juggling' => '1f939', 'light_skin_tone' => '1f3fb', 'mediumlight_skin_tone' => '1f3fc', 'medium_skin_tone' => '1f3fd', 'mediumdark_skin_tone' => '1f3fe', 'dark_skin_tone' => '1f3ff',
		'gorilla' => '1f98d', 'fox' => '1f98a', 'deer' => '1f98c', 'rhinoceros' => '1f98f', 'bat' => '1f987', 'eagle' => '1f985', 'duck' => '1f986', 'owl' => '1f989', 'lizard' => '1f98e', 'shark' => '1f988',
		'butterfly' => '1f98b', 'wilted_flower' => '1f940', 'kiwi_fruit' => '1f95d', 'avocado' => '1f951', 'potato' => '1f954', 'carrot' => '1f955', 'cucumber' => '1f952', 'peanuts' => '1f95c', 'croissant' => '1f950', 'baguette_bread' => '1f956',
		'pancakes' => '1f95e', 'bacon' => '1f953', 'stuffed_flatbread' => '1f959', 'shallow_pan_of_food' => '1f958', 'green_salad' => '1f957', 'shrimp' => '1f990', 'squid' => '1f991', 'glass_of_milk' => '1f95b', 'clinking_glasses' => '1f942', 'tumbler_glass' => '1f943',
		'spoon' => '1f944', 'motor_scooter' => '1f6f5', 'kick_scooter' => '1f6f4', 'canoe' => '1f6f6', 'umbrella' => '2602', 'snowman' => '2603', '1st_place_medal' => '1f947', '2nd_place_medal' => '1f948', '3rd_place_medal' => '1f949', 'boxing_glove' => '1f94a',
		'martial_arts_uniform' => '1f94b', 'goal_net' => '1f945', 'envelope' => '2709', 'shopping_cart' => '1f6d2', 'eject_button' => '23cf', 'medical_symbol' => '2695', 'shinto_shrine' => '26e9', 'fast_forward' => '23e9', 'hash' => '0023', 'asterisk' => '002a',
		'regional_indicator_z' => '1f1ff', 'regional_indicator_y' => '1f1fe', 'regional_indicator_x' => '1f1fd', 'regional_indicator_w' => '1f1fc', 'regional_indicator_v' => '1f1fb', 'regional_indicator_t' => '1f1f9', 'regional_indicator_s' => '1f1f8', 'regional_indicator_r' => '1f1f7', 'regional_indicator_q' => '1f1f6', 'regional_indicator_p' => '1f1f5',
		'regional_indicator_o' => '1f1f4', 'regional_indicator_n' => '1f1f3', 'regional_indicator_m' => '1f1f2', 'regional_indicator_l' => '1f1f1', 'regional_indicator_k' => '1f1f0', 'regional_indicator_j' => '1f1ef', 'regional_indicator_i' => '1f1ee', 'regional_indicator_h' => '1f1ed', 'regional_indicator_g' => '1f1ec', 'regional_indicator_e' => '1f1ea',
		'regional_indicator_d' => '1f1e9', 'regional_indicator_c' => '1f1e8', 'regional_indicator_b' => '1f1e7', 'regional_indicator_a' => '1f1e6'
	);

	private $smileys_url;

	/**
	 * Emoji constructor.
	 *
	 * @param $smileys_url
	 */
	public function __construct($smileys_url)
	{
		$this->smileys_url = $smileys_url;
	}

	/**
	 * Simple search and replace function
	 *
	 * What it does:
	 * - Finds emoji tags outside of code tags and converts them to images
	 * - Called from integrate_pre_bbc_parser
	 *
	 * @param string $string
	 * @return string
	 */
	public static function emojiNameToImage($string)
	{
		global $modSettings;

		$emoji = new Emoji(htmlspecialchars($modSettings['smileys_url']) . '/' . $modSettings['emoji_selection']);

		// Find all emoji tags outside code tags
		$parts = preg_split('~(\[/code]|\[code(?:=[^]]+)?])~i', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

		// Only converts :tags: outside.
		for ($i = 0, $n = count($parts); $i < $n; $i++)
		{
			// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
			if ($i % 4 == 0)
			{
				// They must be at the start of a line, or have a leading space or be after a bbc ] tag
				$parts[$i] = preg_replace_callback('~(\s?|^|]|<br />)(:([-+\w]+):\s?)~si', [$emoji, 'emojiToImage_Callback'], $parts[$i]);
			}
		}

		return implode('', $parts);
	}

	/**
	 * Callback for preg replace in shortnameToImage function
	 *
	 * @param array $m results form preg_replace_callback
	 * @return string
	 */
	private function emojiToImage_Callback($m)
	{
		static $smileys_url = null;

		// No :tag: found or not a complete result, return
		if ((!is_array($m)) || (!isset($m[3])) || (empty($m[3])))
		{
			return $m[0];
		}

		// If its not a known tag, just return what was passed
		if (!isset($this->shortcode_replace[$m[3]]))
		{
			return $m[0];
		}

		// Otherwise we have some Emoji :dancer:
		$filename = $this->smileys_url . '/' . $this->shortcode_replace[$m[3]] . '.svg';
		$alt = strtr($m[2], array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;'));
		$title = strtr(htmlspecialchars($m[3]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;'));

		return $m[1] . '<img class="smiley emoji" src="' . $filename . '" alt="' . $alt . '" title="' . $title  . '" />';
	}
}
