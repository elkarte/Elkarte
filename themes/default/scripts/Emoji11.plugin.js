/*!
 * @package Emoji for ElkArte
 * @author Spuds
 * @copyright (c) 2011-2017 Spuds
 * @license This Source Code is subject to the terms of the Mozilla Public License
 * version 1.1 (the "License"). You can obtain a copy of the License at
 * http://mozilla.org/MPL/1.1/.
 *
 * This handles the population of the emoji selection drop down and the rendering of
 * emoji when toggling from source to wizzy
 *
 * @version 1.0.2
 *
 */

var disableDrafts = false;

(function($, window, document) {
	'use strict';

	// Editor instance
	var editor,
		rangeHelper;

	// These are all we know, but we can only remember about 6 of them
	var emojies = [
		{name: '-1', key: '1f44e'},
		{name: '+1', key: '1f44d'},
		{name: '100', key: '1f4af'},
		{name: '1234', key: '1f522'},
		{name: '8ball', key: '1f3b1'},
		{name: 'a', key: '1f170'},
		{name: 'ab', key: '1f18e'},
		{name: 'abc', key: '1f524'},
		{name: 'abcd', key: '1f521'},
		{name: 'accept', key: '1f251'},
		{name: 'aerial_tramway', key: '1f6a1'},
		{name: 'airplane', key: '2708'},
		{name: 'alarm_clock', key: '23f0'},
		{name: 'alien', key: '1f47d'},
		{name: 'ambulance', key: '1f691'},
		{name: 'anchor', key: '2693'},
		{name: 'angel', key: '1f47c'},
		{name: 'anger', key: '1f4a2'},
		{name: 'angry', key: '1f620'},
		{name: 'anguished', key: '1f626'},
		{name: 'anguished', key: '1f627'},
		{name: 'ant', key: '1f41c'},
		{name: 'apple', key: '1f34e'},
		{name: 'aquarius', key: '2652'},
		{name: 'aries', key: '2648'},
		{name: 'arrow_backward', key: '25c0'},
		{name: 'arrow_double_down', key: '23ec'},
		{name: 'arrow_double_up', key: '23eb'},
		{name: 'arrow_down_small', key: '1f53d'},
		{name: 'arrow_down', key: '2b07'},
		{name: 'arrow_forward', key: '25b6'},
		{name: 'arrow_heading_down', key: '2935'},
		{name: 'arrow_heading_up', key: '2934'},
		{name: 'arrow_left', key: '2b05'},
		{name: 'arrow_lower_left', key: '2199'},
		{name: 'arrow_lower_right', key: '2198'},
		{name: 'arrow_right_hook', key: '21aa'},
		{name: 'arrow_right', key: '27a1'},
		{name: 'arrow_up_down', key: '2195'},
		{name: 'arrow_up_small', key: '1f53c'},
		{name: 'arrow_up', key: '2b06'},
		{name: 'arrow_upper_left', key: '2196'},
		{name: 'arrow_upper_right', key: '2197'},
		{name: 'arrows_clockwise', key: '1f503'},
		{name: 'arrows_counterclockwise', key: '1f504'},
		{name: 'art', key: '1f3a8'},
		{name: 'articulated_lorry', key: '1f69b'},
		{name: 'astonished', key: '1f632'},
		{name: 'athletic_shoe', key: '1f45f'},
		{name: 'atm', key: '1f3e7'},
		{name: 'b', key: '1f171'},
		{name: 'baby_bottle', key: '1f37c'},
		{name: 'baby_chick', key: '1f424'},
		{name: 'baby_symbol', key: '1f6bc'},
		{name: 'baby', key: '1f476'},
		{name: 'back', key: '1f519'},
		{name: 'baggage_claim', key: '1f6c4'},
		{name: 'balloon', key: '1f388'},
		{name: 'ballot_box_with_check', key: '2611'},
		{name: 'bamboo', key: '1f38d'},
		{name: 'banana', key: '1f34c'},
		{name: 'bangbang', key: '203c'},
		{name: 'bank', key: '1f3e6'},
		{name: 'bar_chart', key: '1f4ca'},
		{name: 'barber', key: '1f488'},
		{name: 'baseball', key: '26be'},
		{name: 'basketball', key: '1f3c0'},
		{name: 'bath', key: '1f6c0'},
		{name: 'bathtub', key: '1f6c1'},
		{name: 'battery', key: '1f50b'},
		{name: 'bear', key: '1f43b'},
		{name: 'bee', key: '1f41d'},
		{name: 'beer', key: '1f37a'},
		{name: 'beers', key: '1f37b'},
		{name: 'beetle', key: '1f41e'},
		{name: 'beginner', key: '1f530'},
		{name: 'bell', key: '1f514'},
		{name: 'bento', key: '1f371'},
		{name: 'bicyclist', key: '1f6b4'},
		{name: 'bike', key: '1f6b2'},
		{name: 'bikini', key: '1f459'},
		{name: 'bird', key: '1f426'},
		{name: 'birthday', key: '1f382'},
		{name: 'black_circle', key: '26ab'},
		{name: 'black_joker', key: '1f0cf'},
		{name: 'black_large_square', key: '2b1b'},
		{name: 'black_medium_small_square', key: '25fe'},
		{name: 'black_medium_square', key: '25fc'},
		{name: 'black_nib', key: '2712'},
		{name: 'black_small_square', key: '25aa'},
		{name: 'black_square_button', key: '1f532'},
		{name: 'blossom', key: '1f33c'},
		{name: 'blowfish', key: '1f421'},
		{name: 'blue_book', key: '1f4d8'},
		{name: 'blue_car', key: '1f699'},
		{name: 'blue_heart', key: '1f499'},
		{name: 'blush', key: '1f60a'},
		{name: 'boar', key: '1f417'},
		{name: 'bomb', key: '1f4a3'},
		{name: 'book', key: '1f4d6'},
		{name: 'bookmark_tabs', key: '1f4d1'},
		{name: 'bookmark', key: '1f516'},
		{name: 'books', key: '1f4da'},
		{name: 'boom', key: '1f4a5'},
		{name: 'boot', key: '1f462'},
		{name: 'bouquet', key: '1f490'},
		{name: 'bow', key: '1f647'},
		{name: 'bowling', key: '1f3b3'},
		{name: 'boy', key: '1f466'},
		{name: 'bread', key: '1f35e'},
		{name: 'bride_with_veil', key: '1f470'},
		{name: 'bridge_at_night', key: '1f309'},
		{name: 'briefcase', key: '1f4bc'},
		{name: 'broken_heart', key: '1f494'},
		{name: 'bug', key: '1f41b'},
		{name: 'bulb', key: '1f4a1'},
		{name: 'bullettrain_front', key: '1f685'},
		{name: 'bullettrain_side', key: '1f684'},
		{name: 'bus', key: '1f68c'},
		{name: 'busstop', key: '1f68f'},
		{name: 'bust_in_silhouette', key: '1f464'},
		{name: 'busts_in_silhouette', key: '1f465'},
		{name: 'cactus', key: '1f335'},
		{name: 'cake', key: '1f370'},
		{name: 'calendar', key: '1f4c6'},
		{name: 'calling', key: '1f4f2'},
		{name: 'camel', key: '1f42b'},
		{name: 'camera', key: '1f4f7'},
		{name: 'cancer', key: '264b'},
		{name: 'candy', key: '1f36c'},
		{name: 'capital_abcd', key: '1f520'},
		{name: 'capricorn', key: '2651'},
		{name: 'card_index', key: '1f4c7'},
		{name: 'carousel_horse', key: '1f3a0'},
		{name: 'cat', key: '1f431'},
		{name: 'cat2', key: '1f408'},
		{name: 'cd', key: '1f4bf'},
		{name: 'chart_with_downwards_trend', key: '1f4c9'},
		{name: 'chart_with_upwards_trend', key: '1f4c8'},
		{name: 'chart', key: '1f4b9'},
		{name: 'checkered_flag', key: '1f3c1'},
		{name: 'cherries', key: '1f352'},
		{name: 'cherry_blossom', key: '1f338'},
		{name: 'chestnut', key: '1f330'},
		{name: 'chicken', key: '1f414'},
		{name: 'children_crossing', key: '1f6b8'},
		{name: 'chocolate_bar', key: '1f36b'},
		{name: 'christmas_tree', key: '1f384'},
		{name: 'church', key: '26ea'},
		{name: 'cinema', key: '1f3a6'},
		{name: 'circus_tent', key: '1f3aa'},
		{name: 'city_dusk', key: '1f306'},
		{name: 'city_sunrise', key: '1f307'},
		{name: 'city_sunset', key: '1f306'},
		{name: 'cl', key: '1f191'},
		{name: 'clap', key: '1f44f'},
		{name: 'clapper', key: '1f3ac'},
		{name: 'clipboard', key: '1f4cb'},
		{name: 'clock1', key: '1f550'},
		{name: 'clock10', key: '1f559'},
		{name: 'clock1030', key: '1f565'},
		{name: 'clock11', key: '1f55a'},
		{name: 'clock1130', key: '1f566'},
		{name: 'clock12', key: '1f55b'},
		{name: 'clock1230', key: '1f567'},
		{name: 'clock130', key: '1f55c'},
		{name: 'clock2', key: '1f551'},
		{name: 'clock230', key: '1f55d'},
		{name: 'clock3', key: '1f552'},
		{name: 'clock330', key: '1f55e'},
		{name: 'clock4', key: '1f553'},
		{name: 'clock430', key: '1f55f'},
		{name: 'clock5', key: '1f554'},
		{name: 'clock530', key: '1f560'},
		{name: 'clock6', key: '1f555'},
		{name: 'clock630', key: '1f561'},
		{name: 'clock7', key: '1f556'},
		{name: 'clock730', key: '1f562'},
		{name: 'clock8', key: '1f557'},
		{name: 'clock830', key: '1f563'},
		{name: 'clock9', key: '1f558'},
		{name: 'clock930', key: '1f564'},
		{name: 'closed_book', key: '1f4d5'},
		{name: 'closed_lock_with_key', key: '1f510'},
		{name: 'closed_umbrella', key: '1f302'},
		{name: 'cloud', key: '2601'},
		{name: 'clubs', key: '2663'},
		{name: 'cn', key: '1f1e8-1f1f3'},
		{name: 'cocktail', key: '1f378'},
		{name: 'coffee', key: '2615'},
		{name: 'cold_sweat', key: '1f630'},
		{name: 'computer', key: '1f4bb'},
		{name: 'confetti_ball', key: '1f38a'},
		{name: 'confounded', key: '1f616'},
		{name: 'confused', key: '1f615'},
		{name: 'congratulations', key: '3297'},
		{name: 'construction_worker', key: '1f477'},
		{name: 'construction', key: '1f6a7'},
		{name: 'convenience_store', key: '1f3ea'},
		{name: 'cookie', key: '1f36a'},
		{name: 'cool', key: '1f192'},
		{name: 'cop', key: '1f46e'},
		{name: 'copyright', key: '00a9'},
		{name: 'corn', key: '1f33d'},
		{name: 'couple_with_heart', key: '1f491'},
		{name: 'couple', key: '1f46b'},
		{name: 'couplekiss', key: '1f48f'},
		{name: 'cow', key: '1f42e'},
		{name: 'cow2', key: '1f404'},
		{name: 'credit_card', key: '1f4b3'},
		{name: 'crescent_moon', key: '1f319'},
		{name: 'crocodile', key: '1f40a'},
		{name: 'crossed_flags', key: '1f38c'},
		{name: 'crown', key: '1f451'},
		{name: 'cry', key: '1f622'},
		{name: 'crying_cat_face', key: '1f63f'},
		{name: 'crystal_ball', key: '1f52e'},
		{name: 'cupid', key: '1f498'},
		{name: 'curly_loop', key: '27b0'},
		{name: 'currency_exchange', key: '1f4b1'},
		{name: 'curry', key: '1f35b'},
		{name: 'custard', key: '1f36e'},
		{name: 'customs', key: '1f6c3'},
		{name: 'cyclone', key: '1f300'},
		{name: 'dancer', key: '1f483'},
		{name: 'dancers', key: '1f46f'},
		{name: 'dango', key: '1f361'},
		{name: 'dart', key: '1f3af'},
		{name: 'dash', key: '1f4a8'},
		{name: 'date', key: '1f4c5'},
		{name: 'de', key: '1f1e9-1f1ea'},
		{name: 'deciduous_tree', key: '1f333'},
		{name: 'department_store', key: '1f3ec'},
		{name: 'diamond_shape_with_a_dot_inside', key: '1f4a0'},
		{name: 'diamonds', key: '2666'},
		{name: 'disappointed_relieved', key: '1f625'},
		{name: 'disappointed', key: '1f61e'},
		{name: 'dizzy_face', key: '1f635'},
		{name: 'dizzy', key: '1f4ab'},
		{name: 'do_not_litter', key: '1f6af'},
		{name: 'dog', key: '1f436'},
		{name: 'dog2', key: '1f415'},
		{name: 'dollar', key: '1f4b5'},
		{name: 'dolls', key: '1f38e'},
		{name: 'dolphin', key: '1f42c'},
		{name: 'door', key: '1f6aa'},
		{name: 'doughnut', key: '1f369'},
		{name: 'dragon_face', key: '1f432'},
		{name: 'dragon', key: '1f409'},
		{name: 'dress', key: '1f457'},
		{name: 'dromedary_camel', key: '1f42a'},
		{name: 'droplet', key: '1f4a7'},
		{name: 'dvd', key: '1f4c0'},
		{name: 'e-mail', key: '1f4e7'},
		{name: 'ear_of_rice', key: '1f33e'},
		{name: 'ear', key: '1f442'},
		{name: 'earth_africa', key: '1f30d'},
		{name: 'earth_americas', key: '1f30e'},
		{name: 'earth_asia', key: '1f30f'},
		{name: 'egg', key: '1f373'},
		{name: 'eggplant', key: '1f346'},
		{name: 'eight_pointed_black_star', key: '2734'},
		{name: 'eight_spoked_asterisk', key: '2733'},
		{name: 'eight', key: '0038-20e3'},
		{name: 'electric_plug', key: '1f50c'},
		{name: 'elephant', key: '1f418'},
		{name: 'elk', key: '1f402'},
		{name: 'email', key: '1f4e7'},
		{name: 'end', key: '1f51a'},
		{name: 'envelope_with_arrow', key: '1f4e9'},
		{name: 'envelope', key: '2709'},
		{name: 'es', key: '1f1ea-1f1f8'},
		{name: 'euro', key: '1f4b6'},
		{name: 'european_castle', key: '1f3f0'},
		{name: 'european_post_office', key: '1f3e4'},
		{name: 'evergreen_tree', key: '1f332'},
		{name: 'exclamation', key: '2757'},
		{name: 'expressionless', key: '1f611'},
		{name: 'eyeglasses', key: '1f453'},
		{name: 'eyes', key: '1f440'},
		{name: 'factory', key: '1f3ed'},
		{name: 'fallen_leaf', key: '1f342'},
		{name: 'family', key: '1f46a'},
		{name: 'fast_forward', key: '23e9'},
		{name: 'fax', key: '1f4e0'},
		{name: 'fearful', key: '1f628'},
		{name: 'feet', key: '1f43e'},
		{name: 'ferris_wheel', key: '1f3a1'},
		{name: 'file_folder', key: '1f4c1'},
		{name: 'fire_engine', key: '1f692'},
		{name: 'fire', key: '1f525'},
		{name: 'fireworks', key: '1f386'},
		{name: 'first_quarter_moon_with_face', key: '1f31b'},
		{name: 'first_quarter_moon', key: '1f313'},
		{name: 'fish_cake', key: '1f365'},
		{name: 'fish', key: '1f41f'},
		{name: 'fishing_pole_and_fish', key: '1f3a3'},
		{name: 'fist', key: '270a'},
		{name: 'five', key: '0035-20e3'},
		{name: 'flags', key: '1f38f'},
		{name: 'flame', key: '1f525'},
		{name: 'flashlight', key: '1f526'},
		{name: 'floppy_disk', key: '1f4be'},
		{name: 'flower_playing_cards', key: '1f3b4'},
		{name: 'flushed', key: '1f633'},
		{name: 'foggy', key: '1f301'},
		{name: 'football', key: '1f3c8'},
		{name: 'footprints', key: '1f463'},
		{name: 'fork_and_knife', key: '1f374'},
		{name: 'fountain', key: '26f2'},
		{name: 'four_leaf_clover', key: '1f340'},
		{name: 'four', key: '0034-20e3'},
		{name: 'fr', key: '1f1eb-1f1f7'},
		{name: 'free', key: '1f193'},
		{name: 'fried_shrimp', key: '1f364'},
		{name: 'fries', key: '1f35f'},
		{name: 'frog', key: '1f438'},
		{name: 'frowning', key: '1f626'},
		{name: 'fuelpump', key: '26fd'},
		{name: 'full_moon_with_face', key: '1f31d'},
		{name: 'full_moon', key: '1f315'},
		{name: 'game_die', key: '1f3b2'},
		{name: 'gb', key: '1f1ec-1f1e7'},
		{name: 'gem', key: '1f48e'},
		{name: 'gemini', key: '264a'},
		{name: 'ghost', key: '1f47b'},
		{name: 'gift_heart', key: '1f49d'},
		{name: 'gift', key: '1f381'},
		{name: 'girl', key: '1f467'},
		{name: 'globe_with_meridians', key: '1f310'},
		{name: 'goat', key: '1f410'},
		{name: 'golf', key: '26f3'},
		{name: 'grandma', key: '1f475'},
		{name: 'grapes', key: '1f347'},
		{name: 'green_apple', key: '1f34f'},
		{name: 'green_book', key: '1f4d7'},
		{name: 'green_heart', key: '1f49a'},
		{name: 'grey_exclamation', key: '2755'},
		{name: 'grey_question', key: '2754'},
		{name: 'grimacing', key: '1f62c'},
		{name: 'grin', key: '1f601'},
		{name: 'grinning', key: '1f600'},
		{name: 'guardsman', key: '1f482'},
		{name: 'guitar', key: '1f3b8'},
		{name: 'gun', key: '1f52b'},
		{name: 'haircut', key: '1f487'},
		{name: 'hamburger', key: '1f354'},
		{name: 'hammer', key: '1f528'},
		{name: 'hamster', key: '1f439'},
		{name: 'handbag', key: '1f45c'},
		{name: 'hankey', key: '1f4a9'},
		{name: 'hash', key: '0023-20e3'},
		{name: 'hatched_chick', key: '1f425'},
		{name: 'hatching_chick', key: '1f423'},
		{name: 'headphones', key: '1f3a7'},
		{name: 'hear_no_evil', key: '1f649'},
		{name: 'heart_decoration', key: '1f49f'},
		{name: 'heart_eyes_cat', key: '1f63b'},
		{name: 'heart_eyes', key: '1f60d'},
		{name: 'heart', key: '2764'},
		{name: 'heartbeat', key: '1f493'},
		{name: 'heartpulse', key: '1f497'},
		{name: 'hearts', key: '2665'},
		{name: 'heavy_check_mark', key: '2714'},
		{name: 'heavy_division_sign', key: '2797'},
		{name: 'heavy_dollar_sign', key: '1f4b2'},
		{name: 'heavy_minus_sign', key: '2796'},
		{name: 'heavy_multiplication_x', key: '2716'},
		{name: 'heavy_plus_sign', key: '2795'},
		{name: 'helicopter', key: '1f681'},
		{name: 'herb', key: '1f33f'},
		{name: 'hibiscus', key: '1f33a'},
		{name: 'high_brightness', key: '1f506'},
		{name: 'high_heel', key: '1f460'},
		{name: 'honey_pot', key: '1f36f'},
		{name: 'horse_racing', key: '1f3c7'},
		{name: 'horse', key: '1f434'},
		{name: 'hospital', key: '1f3e5'},
		{name: 'hotel', key: '1f3e8'},
		{name: 'hotsprings', key: '2668'},
		{name: 'hourglass_flowing_sand', key: '23f3'},
		{name: 'hourglass', key: '231b'},
		{name: 'house_with_garden', key: '1f3e1'},
		{name: 'house', key: '1f3e0'},
		{name: 'hushed', key: '1f62f'},
		{name: 'ice_cream', key: '1f368'},
		{name: 'icecream', key: '1f366'},
		{name: 'id', key: '1f194'},
		{name: 'ideograph_advantage', key: '1f250'},
		{name: 'imp', key: '1f47f'},
		{name: 'inbox_tray', key: '1f4e5'},
		{name: 'incoming_envelope', key: '1f4e8'},
		{name: 'information_desk_person', key: '1f481'},
		{name: 'information_source', key: '2139'},
		{name: 'innocent', key: '1f607'},
		{name: 'interrobang', key: '2049'},
		{name: 'iphone', key: '1f4f1'},
		{name: 'it', key: '1f1ee-1f1f9'},
		{name: 'izakaya_lantern', key: '1f3ee'},
		{name: 'jack_o_lantern', key: '1f383'},
		{name: 'japan', key: '1f5fe'},
		{name: 'japanese_castle', key: '1f3ef'},
		{name: 'japanese_goblin', key: '1f47a'},
		{name: 'japanese_ogre', key: '1f479'},
		{name: 'jeans', key: '1f456'},
		{name: 'joy_cat', key: '1f639'},
		{name: 'joy', key: '1f602'},
		{name: 'jp', key: '1f1ef-1f1f5'},
		{name: 'key', key: '1f511'},
		{name: 'keycap_ten', key: '1f51f'},
		{name: 'kimono', key: '1f458'},
		{name: 'kiss', key: '1f48b'},
		{name: 'kissing_cat', key: '1f63d'},
		{name: 'kissing_closed_eyes', key: '1f61a'},
		{name: 'kissing_heart', key: '1f618'},
		{name: 'kissing_smiling_eyes', key: '1f619'},
		{name: 'kissing', key: '1f617'},
		{name: 'knife', key: '1f52a'},
		{name: 'koala', key: '1f428'},
		{name: 'koko', key: '1f201'},
		{name: 'kr', key: '1f1f0-1f1f7'},
		{name: 'large_blue_circle', key: '1f535'},
		{name: 'large_blue_diamond', key: '1f537'},
		{name: 'large_orange_diamond', key: '1f536'},
		{name: 'last_quarter_moon_with_face', key: '1f31c'},
		{name: 'last_quarter_moon', key: '1f317'},
		{name: 'laughing', key: '1f606'},
		{name: 'leaves', key: '1f343'},
		{name: 'ledger', key: '1f4d2'},
		{name: 'left_luggage', key: '1f6c5'},
		{name: 'left_right_arrow', key: '2194'},
		{name: 'leftwards_arrow_with_hook', key: '21a9'},
		{name: 'lemon', key: '1f34b'},
		{name: 'leo', key: '264c'},
		{name: 'leopard', key: '1f406'},
		{name: 'libra', key: '264e'},
		{name: 'light_rail', key: '1f688'},
		{name: 'link', key: '1f517'},
		{name: 'lips', key: '1f444'},
		{name: 'lipstick', key: '1f484'},
		{name: 'lock_with_ink_pen', key: '1f50f'},
		{name: 'lock', key: '1f512'},
		{name: 'lollipop', key: '1f36d'},
		{name: 'loop', key: '27bf'},
		{name: 'loud_sound', key: '1f50a'},
		{name: 'loudspeaker', key: '1f4e2'},
		{name: 'love_hotel', key: '1f3e9'},
		{name: 'love_letter', key: '1f48c'},
		{name: 'low_brightness', key: '1f505'},
		{name: 'm', key: '24c2'},
		{name: 'mag_right', key: '1f50e'},
		{name: 'mag', key: '1f50d'},
		{name: 'mahjong', key: '1f004'},
		{name: 'mailbox_closed', key: '1f4ea'},
		{name: 'mailbox_with_mail', key: '1f4ec'},
		{name: 'mailbox_with_no_mail', key: '1f4ed'},
		{name: 'mailbox', key: '1f4eb'},
		{name: 'man_with_gua_pi_mao', key: '1f472'},
		{name: 'man_with_turban', key: '1f473'},
		{name: 'man', key: '1f468'},
		{name: 'mans_shoe', key: '1f45e'},
		{name: 'maple_leaf', key: '1f341'},
		{name: 'mask', key: '1f637'},
		{name: 'massage', key: '1f486'},
		{name: 'meat_on_bone', key: '1f356'},
		{name: 'mega', key: '1f4e3'},
		{name: 'melon', key: '1f348'},
		{name: 'mens', key: '1f6b9'},
		{name: 'metro', key: '1f687'},
		{name: 'microphone', key: '1f3a4'},
		{name: 'microscope', key: '1f52c'},
		{name: 'milky_way', key: '1f30c'},
		{name: 'minibus', key: '1f690'},
		{name: 'minidisc', key: '1f4bd'},
		{name: 'mobile_phone_off', key: '1f4f4'},
		{name: 'money_with_wings', key: '1f4b8'},
		{name: 'moneybag', key: '1f4b0'},
		{name: 'monkey_face', key: '1f435'},
		{name: 'monkey', key: '1f412'},
		{name: 'monorail', key: '1f69d'},
		{name: 'mortar_board', key: '1f393'},
		{name: 'mount_fuji', key: '1f5fb'},
		{name: 'mountain_bicyclist', key: '1f6b5'},
		{name: 'mountain_cableway', key: '1f6a0'},
		{name: 'mountain_railway', key: '1f69e'},
		{name: 'mouse', key: '1f42d'},
		{name: 'mouse2', key: '1f401'},
		{name: 'movie_camera', key: '1f3a5'},
		{name: 'moyai', key: '1f5ff'},
		{name: 'muscle', key: '1f4aa'},
		{name: 'mushroom', key: '1f344'},
		{name: 'musical_keyboard', key: '1f3b9'},
		{name: 'musical_note', key: '1f3b5'},
		{name: 'musical_score', key: '1f3bc'},
		{name: 'mute', key: '1f507'},
		{name: 'nail_care', key: '1f485'},
		{name: 'name_badge', key: '1f4db'},
		{name: 'necktie', key: '1f454'},
		{name: 'negative_squared_cross_mark', key: '274e'},
		{name: 'neutral_face', key: '1f610'},
		{name: 'new_moon_with_face', key: '1f31a'},
		{name: 'new_moon', key: '1f311'},
		{name: 'new', key: '1f195'},
		{name: 'newspaper', key: '1f4f0'},
		{name: 'ng', key: '1f196'},
		{name: 'night_with_stars', key: '1f303'},
		{name: 'nine', key: '0039-20e3'},
		{name: 'no_bell', key: '1f515'},
		{name: 'no_bicycles', key: '1f6b3'},
		{name: 'no_entry_sign', key: '1f6ab'},
		{name: 'no_entry', key: '26d4'},
		{name: 'no_good', key: '1f645'},
		{name: 'no_mobile_phones', key: '1f4f5'},
		{name: 'no_mouth', key: '1f636'},
		{name: 'no_pedestrians', key: '1f6b7'},
		{name: 'no_smoking', key: '1f6ad'},
		{name: 'non-potable_water', key: '1f6b1'},
		{name: 'nose', key: '1f443'},
		{name: 'notebook_with_decorative_cover', key: '1f4d4'},
		{name: 'notebook', key: '1f4d3'},
		{name: 'notes', key: '1f3b6'},
		{name: 'nut_and_bolt', key: '1f529'},
		{name: 'o', key: '2b55'},
		{name: 'o2', key: '1f17e'},
		{name: 'ocean', key: '1f30a'},
		{name: 'octopus', key: '1f419'},
		{name: 'oden', key: '1f362'},
		{name: 'office', key: '1f3e2'},
		{name: 'ok_hand', key: '1f44c'},
		{name: 'ok_woman', key: '1f646'},
		{name: 'ok', key: '1f197'},
		{name: 'older_man', key: '1f474'},
		{name: 'older_woman', key: '1f475'},
		{name: 'on', key: '1f51b'},
		{name: 'oncoming_automobile', key: '1f698'},
		{name: 'oncoming_bus', key: '1f68d'},
		{name: 'oncoming_police_car', key: '1f694'},
		{name: 'oncoming_taxi', key: '1f696'},
		{name: 'one', key: '0031-20e3'},
		{name: 'open_file_folder', key: '1f4c2'},
		{name: 'open_hands', key: '1f450'},
		{name: 'open_mouth', key: '1f62e'},
		{name: 'ophiuchus', key: '26ce'},
		{name: 'orange_book', key: '1f4d9'},
		{name: 'outbox_tray', key: '1f4e4'},
		{name: 'ox', key: '1f402'},
		{name: 'package', key: '1f4e6'},
		{name: 'page_facing_up', key: '1f4c4'},
		{name: 'page_with_curl', key: '1f4c3'},
		{name: 'pager', key: '1f4df'},
		{name: 'palm_tree', key: '1f334'},
		{name: 'panda_face', key: '1f43c'},
		{name: 'paperclip', key: '1f4ce'},
		{name: 'parking', key: '1f17f'},
		{name: 'part_alternation_mark', key: '303d'},
		{name: 'partly_sunny', key: '26c5'},
		{name: 'passport_control', key: '1f6c2'},
		{name: 'peach', key: '1f351'},
		{name: 'pear', key: '1f350'},
		{name: 'pencil', key: '1f4dd'},
		{name: 'pencil2', key: '270f'},
		{name: 'penguin', key: '1f427'},
		{name: 'pensive', key: '1f614'},
		{name: 'performing_arts', key: '1f3ad'},
		{name: 'persevere', key: '1f623'},
		{name: 'person_frowning', key: '1f64d'},
		{name: 'person_with_blond_hair', key: '1f471'},
		{name: 'person_with_pouting_face', key: '1f64e'},
		{name: 'pig_nose', key: '1f43d'},
		{name: 'pig', key: '1f437'},
		{name: 'pig2', key: '1f416'},
		{name: 'pill', key: '1f48a'},
		{name: 'pineapple', key: '1f34d'},
		{name: 'pisces', key: '2653'},
		{name: 'pizza', key: '1f355'},
		{name: 'point_down', key: '1f447'},
		{name: 'point_left', key: '1f448'},
		{name: 'point_right', key: '1f449'},
		{name: 'point_up_2', key: '1f446'},
		{name: 'point_up', key: '261d'},
		{name: 'police_car', key: '1f693'},
		{name: 'poo', key: '1f4a9'},
		{name: 'poodle', key: '1f429'},
		{name: 'poop', key: '1f4a9'},
		{name: 'post_office', key: '1f3e3'},
		{name: 'postal_horn', key: '1f4ef'},
		{name: 'postbox', key: '1f4ee'},
		{name: 'potable_water', key: '1f6b0'},
		{name: 'pouch', key: '1f45d'},
		{name: 'poultry_leg', key: '1f357'},
		{name: 'pound', key: '1f4b7'},
		{name: 'pouting_cat', key: '1f63e'},
		{name: 'pray', key: '1f64f'},
		{name: 'princess', key: '1f478'},
		{name: 'punch', key: '1f44a'},
		{name: 'purple_heart', key: '1f49c'},
		{name: 'purse', key: '1f45b'},
		{name: 'pushpin', key: '1f4cc'},
		{name: 'put_litter_in_its_place', key: '1f6ae'},
		{name: 'question', key: '2753'},
		{name: 'rabbit', key: '1f430'},
		{name: 'rabbit2', key: '1f407'},
		{name: 'racehorse', key: '1f40e'},
		{name: 'radio_button', key: '1f518'},
		{name: 'radio', key: '1f4fb'},
		{name: 'rage', key: '1f621'},
		{name: 'railway_car', key: '1f683'},
		{name: 'rainbow', key: '1f308'},
		{name: 'raised_hand', key: '270b'},
		{name: 'raised_hands', key: '1f64c'},
		{name: 'raising_hand', key: '1f64b'},
		{name: 'ram', key: '1f40f'},
		{name: 'ramen', key: '1f35c'},
		{name: 'rat', key: '1f400'},
		{name: 'recycle', key: '267b'},
		{name: 'red_car', key: '1f697'},
		{name: 'red_circle', key: '1f534'},
		{name: 'registered', key: '00ae'},
		{name: 'relaxed', key: '263a'},
		{name: 'relieved', key: '1f60c'},
		{name: 'repeat_one', key: '1f502'},
		{name: 'repeat', key: '1f501'},
		{name: 'restroom', key: '1f6bb'},
		{name: 'revolving_hearts', key: '1f49e'},
		{name: 'rewind', key: '23ea'},
		{name: 'ribbon', key: '1f380'},
		{name: 'rice_ball', key: '1f359'},
		{name: 'rice_cracker', key: '1f358'},
		{name: 'rice_scene', key: '1f391'},
		{name: 'rice', key: '1f35a'},
		{name: 'ring', key: '1f48d'},
		{name: 'rocket', key: '1f680'},
		{name: 'roller_coaster', key: '1f3a2'},
		{name: 'rooster', key: '1f413'},
		{name: 'rose', key: '1f339'},
		{name: 'rotating_light', key: '1f6a8'},
		{name: 'round_pushpin', key: '1f4cd'},
		{name: 'rowboat', key: '1f6a3'},
		{name: 'ru', key: '1f1f7-1f1fa'},
		{name: 'rugby_football', key: '1f3c9'},
		{name: 'runner', key: '1f3c3'},
		{name: 'running_shirt_with_sash', key: '1f3bd'},
		{name: 'sa', key: '1f202'},
		{name: 'sagittarius', key: '2650'},
		{name: 'sailboat', key: '26f5'},
		{name: 'sake', key: '1f376'},
		{name: 'sandal', key: '1f461'},
		{name: 'santa', key: '1f385'},
		{name: 'satellite', key: '1f4e1'},
		{name: 'satisfied', key: '1f606'},
		{name: 'saxophone', key: '1f3b7'},
		{name: 'school_satchel', key: '1f392'},
		{name: 'school', key: '1f3eb'},
		{name: 'scissors', key: '2702'},
		{name: 'scorpius', key: '264f'},
		{name: 'scream_cat', key: '1f640'},
		{name: 'scream', key: '1f631'},
		{name: 'scroll', key: '1f4dc'},
		{name: 'seat', key: '1f4ba'},
		{name: 'secret', key: '3299'},
		{name: 'see_no_evil', key: '1f648'},
		{name: 'seedling', key: '1f331'},
		{name: 'seven', key: '0037-20e3'},
		{name: 'shaved_ice', key: '1f367'},
		{name: 'sheep', key: '1f411'},
		{name: 'shell', key: '1f41a'},
		{name: 'ship', key: '1f6a2'},
		{name: 'shirt', key: '1f455'},
		{name: 'shower', key: '1f6bf'},
		{name: 'signal_strength', key: '1f4f6'},
		{name: 'six_pointed_star', key: '1f52f'},
		{name: 'six', key: '0036-20e3'},
		{name: 'skeleton', key: '1f480'},
		{name: 'ski', key: '1f3bf'},
		{name: 'skull', key: '1f480'},
		{name: 'sleeping', key: '1f634'},
		{name: 'sleepy', key: '1f62a'},
		{name: 'slot_machine', key: '1f3b0'},
		{name: 'small_blue_diamond', key: '1f539'},
		{name: 'small_orange_diamond', key: '1f538'},
		{name: 'small_red_triangle_down', key: '1f53b'},
		{name: 'small_red_triangle', key: '1f53a'},
		{name: 'smile_cat', key: '1f638'},
		{name: 'smile', key: '1f604'},
		{name: 'smiley_cat', key: '1f63a'},
		{name: 'smiley', key: '1f603'},
		{name: 'smiling_imp', key: '1f608'},
		{name: 'smirk_cat', key: '1f63c'},
		{name: 'smirk', key: '1f60f'},
		{name: 'smoking', key: '1f6ac'},
		{name: 'snail', key: '1f40c'},
		{name: 'snake', key: '1f40d'},
		{name: 'snowboarder', key: '1f3c2'},
		{name: 'snowflake', key: '2744'},
		{name: 'snowman', key: '26c4'},
		{name: 'sob', key: '1f62d'},
		{name: 'soccer', key: '26bd'},
		{name: 'soon', key: '1f51c'},
		{name: 'sos', key: '1f198'},
		{name: 'sound', key: '1f509'},
		{name: 'space_invader', key: '1f47e'},
		{name: 'spades', key: '2660'},
		{name: 'spaghetti', key: '1f35d'},
		{name: 'sparkle', key: '2747'},
		{name: 'sparkler', key: '1f387'},
		{name: 'sparkles', key: '2728'},
		{name: 'sparkling_heart', key: '1f496'},
		{name: 'speak_no_evil', key: '1f64a'},
		{name: 'speaker', key: '1f508'},
		{name: 'speech_balloon', key: '1f4ac'},
		{name: 'speedboat', key: '1f6a4'},
		{name: 'star', key: '2b50'},
		{name: 'star2', key: '1f31f'},
		{name: 'stars', key: '1f320'},
		{name: 'station', key: '1f689'},
		{name: 'statue_of_liberty', key: '1f5fd'},
		{name: 'steam_locomotive', key: '1f682'},
		{name: 'stew', key: '1f372'},
		{name: 'straight_ruler', key: '1f4cf'},
		{name: 'strawberry', key: '1f353'},
		{name: 'stuck_out_tongue_closed_eyes', key: '1f61d'},
		{name: 'stuck_out_tongue_winking_eye', key: '1f61c'},
		{name: 'stuck_out_tongue', key: '1f61b'},
		{name: 'sun_with_face', key: '1f31e'},
		{name: 'sunflower', key: '1f33b'},
		{name: 'sunglasses', key: '1f60e'},
		{name: 'sunny', key: '2600'},
		{name: 'sunrise_over_mountains', key: '1f304'},
		{name: 'sunrise', key: '1f305'},
		{name: 'surfer', key: '1f3c4'},
		{name: 'sushi', key: '1f363'},
		{name: 'suspension_railway', key: '1f69f'},
		{name: 'sweat_drops', key: '1f4a6'},
		{name: 'sweat_smile', key: '1f605'},
		{name: 'sweat', key: '1f613'},
		{name: 'sweet_potato', key: '1f360'},
		{name: 'swimmer', key: '1f3ca'},
		{name: 'symbols', key: '1f523'},
		{name: 'syringe', key: '1f489'},
		{name: 'tada', key: '1f389'},
		{name: 'tanabata_tree', key: '1f38b'},
		{name: 'tangerine', key: '1f34a'},
		{name: 'taurus', key: '2649'},
		{name: 'taxi', key: '1f695'},
		{name: 'tea', key: '1f375'},
		{name: 'telephone_receiver', key: '1f4de'},
		{name: 'telephone', key: '260e'},
		{name: 'telescope', key: '1f52d'},
		{name: 'tennis', key: '1f3be'},
		{name: 'tent', key: '26fa'},
		{name: 'thought_balloon', key: '1f4ad'},
		{name: 'three', key: '0033-20e3'},
		{name: 'thumbsdown', key: '1f44e'},
		{name: 'thumbsup', key: '1f44d'},
		{name: 'ticket', key: '1f3ab'},
		{name: 'tiger', key: '1f42f'},
		{name: 'tiger2', key: '1f405'},
		{name: 'tired_face', key: '1f62b'},
		{name: 'tm', key: '2122'},
		{name: 'toilet', key: '1f6bd'},
		{name: 'tokyo_tower', key: '1f5fc'},
		{name: 'tomato', key: '1f345'},
		{name: 'tongue', key: '1f445'},
		{name: 'top', key: '1f51d'},
		{name: 'tophat', key: '1f3a9'},
		{name: 'tractor', key: '1f69c'},
		{name: 'traffic_light', key: '1f6a5'},
		{name: 'train', key: '1f68b'},
		{name: 'train2', key: '1f686'},
		{name: 'tram', key: '1f68a'},
		{name: 'triangular_flag_on_post', key: '1f6a9'},
		{name: 'triangular_ruler', key: '1f4d0'},
		{name: 'trident', key: '1f531'},
		{name: 'triumph', key: '1f624'},
		{name: 'trolleybus', key: '1f68e'},
		{name: 'trophy', key: '1f3c6'},
		{name: 'tropical_drink', key: '1f379'},
		{name: 'tropical_fish', key: '1f420'},
		{name: 'truck', key: '1f69a'},
		{name: 'trumpet', key: '1f3ba'},
		{name: 'tulip', key: '1f337'},
		{name: 'turtle', key: '1f422'},
		{name: 'tv', key: '1f4fa'},
		{name: 'twisted_rightwards_arrows', key: '1f500'},
		{name: 'two_hearts', key: '1f495'},
		{name: 'two_men_holding_hands', key: '1f46c'},
		{name: 'two_women_holding_hands', key: '1f46d'},
		{name: 'two', key: '0032-20e3'},
		{name: 'u5272', key: '1f239'},
		{name: 'u5408', key: '1f234'},
		{name: 'u55b6', key: '1f23a'},
		{name: 'u6307', key: '1f22f'},
		{name: 'u6708', key: '1f237'},
		{name: 'u6709', key: '1f236'},
		{name: 'u6e80', key: '1f235'},
		{name: 'u7121', key: '1f21a'},
		{name: 'u7533', key: '1f238'},
		{name: 'u7981', key: '1f232'},
		{name: 'u7a7a', key: '1f233'},
		{name: 'umbrella', key: '2614'},
		{name: 'unamused', key: '1f612'},
		{name: 'underage', key: '1f51e'},
		{name: 'unlock', key: '1f513'},
		{name: 'up', key: '1f199'},
		{name: 'us', key: '1f1fa-1f1f8'},
		{name: 'v', key: '270c'},
		{name: 'vertical_traffic_light', key: '1f6a6'},
		{name: 'vhs', key: '1f4fc'},
		{name: 'vibration_mode', key: '1f4f3'},
		{name: 'video_camera', key: '1f4f9'},
		{name: 'video_game', key: '1f3ae'},
		{name: 'violin', key: '1f3bb'},
		{name: 'virgo', key: '264d'},
		{name: 'volcano', key: '1f30b'},
		{name: 'vs', key: '1f19a'},
		{name: 'walking', key: '1f6b6'},
		{name: 'waning_crescent_moon', key: '1f318'},
		{name: 'waning_gibbous_moon', key: '1f316'},
		{name: 'warning', key: '26a0'},
		{name: 'watch', key: '231a'},
		{name: 'water_buffalo', key: '1f403'},
		{name: 'watermelon', key: '1f349'},
		{name: 'wave', key: '1f44b'},
		{name: 'wavy_dash', key: '3030'},
		{name: 'waxing_crescent_moon', key: '1f312'},
		{name: 'waxing_gibbous_moon', key: '1f314'},
		{name: 'wc', key: '1f6be'},
		{name: 'weary', key: '1f629'},
		{name: 'wedding', key: '1f492'},
		{name: 'whale', key: '1f433'},
		{name: 'whale2', key: '1f40b'},
		{name: 'wheelchair', key: '267f'},
		{name: 'white_check_mark', key: '2705'},
		{name: 'white_circle', key: '26aa'},
		{name: 'white_flower', key: '1f4ae'},
		{name: 'white_large_square', key: '2b1c'},
		{name: 'white_medium_small_square', key: '25fd'},
		{name: 'white_medium_square', key: '25fb'},
		{name: 'white_small_square', key: '25ab'},
		{name: 'white_square_button', key: '1f533'},
		{name: 'wind_chime', key: '1f390'},
		{name: 'wine_glass', key: '1f377'},
		{name: 'wink', key: '1f609'},
		{name: 'wolf', key: '1f43a'},
		{name: 'woman', key: '1f469'},
		{name: 'womans_clothes', key: '1f45a'},
		{name: 'womans_hat', key: '1f452'},
		{name: 'wombat', key: '1f428'},
		{name: 'womens', key: '1f6ba'},
		{name: 'worried', key: '1f61f'},
		{name: 'wrench', key: '1f527'},
		{name: 'x', key: '274c'},
		{name: 'yellow_heart', key: '1f49b'},
		{name: 'yen', key: '1f4b4'},
		{name: 'yum', key: '1f60b'},
		{name: 'zap', key: '26a1'},
		{name: 'zero', key: '0030-20e3'},
		{name: 'zzz', key: '1f4a4'}
	];

	/**
	 * Load in any options
	 *
	 * @param {object} options
	 */
	function elk_Emoji(options) {
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);
	}

	/**
	 * Helper function to see if a :tag: emoji is one we know
	 *
	 * @param {string} emoji
	 */
	elk_Emoji.prototype.emojiExists = function (emoji) {
		return emojies.some(function (el) {
			return el.name === emoji;
		});
	};

	/**
	 * Attach atwho to the passed $element so we create a pull down list
	 *
	 * @param {object} oEmoji
	 * @param {object} $element
	 * @param {object} oIframeWindow
	 */
	elk_Emoji.prototype.attachAtWho = function (oEmoji, $element, oIframeWindow) {
		// Create the dropdown selection list
		// Inserts the site image location when one is selected.
		// Uses the CDN for the pulldown image to reduce site calls
		// If you decide to use the github gemoji images then
		// change tpl to src='https://assets-cdn.github.com/images/icons/emoji/${key}.png'
		var tpl;

		if (oEmoji.opts.emoji_group === 'twitter')
			tpl = "https://twemoji.maxcdn.com/72x72/${key}.png";
		else if (oEmoji.opts.emoji_group === 'github')
			tpl = "https://assets-cdn.github.com/images/icons/emoji/${key}.png";
		else
			tpl = "http://cdn.jsdelivr.net/emojione/assets/png/${key}.png";

		$element.atwho({
			at: ":",
			data: emojies,
			maxLen: 15,
			limit: 8,
			acceptSpaceBar: true,
			displayTpl: "<li data-value=':${name}:'>${name}<img style='max-width:18px;float:right;' src='" + tpl + "' /></li>",
			insertTpl: "<img style='max-width:18px;padding:0 2px;vertical-align:bottom;' data-sceditor-emoticon=':${name}:' alt=':${key}:' title='${name}' src='" + oEmoji.opts.emoji_url + "/${name}.png' />",
			callbacks: {
				filter: function (query, items, search_key) {
					// Don't show the list until they have entered at least two characters
					if (query.length < 2)
						return [];
					else
						return items;
				},
				tpl_eval: function (tpl, map) {
					// The jsdeliver CDN (open emoji) needs the key in uppercase
					if (oEmoji.opts.emoji_group !== 'twitter')
						map.key = map.key.toUpperCase();
					try {
						return tpl.replace(/\$\{([^\}]*)\}/g, function(tag, key, pos) {
							return map[key];
						});
					} catch (_error) {
						if ('console' in window)
							window.console.info(_error);
						return "";
					}
				},
				beforeReposition: function (offset) {
					// We only need to adjust when in wysiwyg
					if (editor.inSourceMode())
						return offset;

					// Lets get the caret position so we can add the mentions box there
					var corrected_offset = findAtPosition();

					offset.top = corrected_offset.top;
					offset.left = corrected_offset.left;

					return offset;
				}
			}
		});

		// Don't save a draft due to a emoji window open/close
		$(oIframeWindow).on("shown.atwho", function (event, offset) {
			disableDrafts = true;
		});
		$(oIframeWindow).on("hidden.atwho", function (event, offset) {
			disableDrafts = false;
		});

		// Attach a click event to the toggle button, can't find a good plugin event to use
		// for this purpose
		if (typeof(oIframeWindow) !== 'undefined') {
			$(".sceditor-button-source").on("click", function (event, offset) {
				// If the button has the active class, we clicked and entered wizzy mode
				if (!$(this).hasClass("active"))
					elk_Emoji.prototype.processEmoji();
			});
		}

		/**
		 * Determine the caret position inside of sceditor's iframe
		 *
		 * What it does:
		 * - Caret.js does not seem to return the correct position for (FF & IE) when
		 * the iframe has vertically scrolled.
		 * - This is an sceditor specific function to return a screen caret position
		 * - Called just before At.js adds the mentions dropdown box
		 * - Finds the @mentions tag and adds an invisible zero width space before it
		 * - Gets the location offset() in the iframe "window" of the added space
		 * - Adjusts for the iframe scroll
		 * - Adds in the iframe container location offset() to main window
		 * - Removes the space, restores the editor range.
		 *
		 * @returns {{}}
		 */
		function findAtPosition() {
			// Get sceditor's RangeHelper for use
			rangeHelper = editor.getRangeHelper();

			// Save the current state
			rangeHelper.saveRange();

			var start = rangeHelper.getMarker('sceditor-start-marker'),
				parent = start.parentNode,
				prev = start.previousSibling,
				offset = {},
				atPos,
				placefinder;

			// Create a placefinder span containing a 'ZERO WIDTH SPACE' Character
			placefinder = start.ownerDocument.createElement('span');
			$(placefinder).text("200B").addClass('placefinder');

			// Look back and find the emoji : tag, so we can insert our span ahead of it
			while (prev) {
				atPos = (prev.nodeValue || '').lastIndexOf(':');

				// Found the start of @mention
				if (atPos > -1) {
					parent.insertBefore(placefinder, prev.splitText(atPos + 1));
					break;
				}

				prev = prev.previousSibling;
			}

			// If we were successful in adding the placefinder
			if (placefinder.parentNode) {
				var $_placefinder = $(placefinder),
					wizzy_scroll = $('#' + oEmoji.opts.editor_id).parent().find('iframe').contents().scrollTop();

				// Determine its Location in the iframe
				offset = $_placefinder.offset();

				// If we have scrolled, then we also need to account for those offsets
				offset.top -= wizzy_scroll;
				offset.top += $_placefinder.height();

				// Remove our placefinder
				$_placefinder.remove();
			}

			// Put things back just like we found them
			rangeHelper.restoreRange();

			// Add in the iframe's offset to get the final location.
			if (offset) {
				var iframeOffset = editor.getContentAreaContainer().offset();

				// Some fudge for the kids
				offset.top += iframeOffset.top + 5;
				offset.left += iframeOffset.left + 5;
			}

			return offset;
		}
	};

	/**
	 * Fetches the HTML from the editor window and updates any emoji :tags: with img tags
	 */
	elk_Emoji.prototype.processEmoji = function () {
		var instance, // sceditor instance
			str, // current html in the editor
			emoji_url = elk_smileys_url.replace("default", "emoji"), // where the emoji images are
			emoji_regex = new RegExp("(:([-+\\w]+):)", "gi"), // find emoji
			code_regex = new RegExp("(</code>|<code(?:[^>]+)?>)", "gi"), // split around code tags
			str_split,
			i,
			n;

		// Get the editors instance and html code from the window
		instance = $('#' + post_box_name).sceditor('instance');
		str = instance.getWysiwygEditorValue(false);

		// Only convert emoji outside <code> tags.
		str_split = str.split(code_regex);
		n = str_split.length;

		// Process the strings
		for (i = 0; i < n; i++) {
			// Only look for emoji outside the code tags
			if (i % 4 === 0) {
				// Search for emoji :tags: and replace known ones with the right image
				str_split[i] = str_split[i].replace(emoji_regex, function (match, tag, shortname) {
					// Replace all valid emoji tags with the image tag
					if ((typeof(shortname) === 'undefined') || (shortname === '') || (!(elk_Emoji.prototype.emojiExists(shortname))))
						return match;
					else
						return '<img data-sceditor-emoticon="' + tag + '" style="max-width:18px;padding:0 2px;vertical-align:bottom;" alt="' + tag + '" title="' + shortname + '" src="' + emoji_url + '/' + shortname + '.png" />';
				});
			}
		}

		// Put it all back together
		str = str_split.join('');

		// Replace the editors html with the update html
		instance.val(str, false);
	};

	/**
	 * Private emoji vars
	 */
	elk_Emoji.prototype.defaults = {};

	/**
	 * Holds all current emoji (defaults + passed options)
	 */
	elk_Emoji.prototype.opts = {};

	/**
	 * Emoji plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the emoji's
	 */
	$.sceditor.plugins.emoji = function () {
		var base = this,
			oEmoji;

		base.init = function() {
			// Grab this instance for use use in oEmoji
			editor = this;
		};

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function () {
			// Init the emoji instance, load in the options
			oEmoji = new elk_Emoji(this.opts.emojiOptions);

			if (typeof(oEmoji.opts.editor_id) === 'undefined')
				oEmoji.opts.editor_id = post_box_name;

			oEmoji.opts.emoji_url = elk_smileys_url.replace("default", "emoji");

			// Attach atwho to the textarea
			oEmoji.attachAtWho(oEmoji, $('#' + oEmoji.opts.editor_id).parent().find('textarea'));

			// Using wysiwyg, then lets attach atwho to it as well
			var instance = $('#' + oEmoji.opts.editor_id).sceditor('instance');
			if (!instance.opts.runWithoutWysiwygSupport) {
				// We need to monitor the iframe window and body to text input
				var oIframe = $('#' + oEmoji.opts.editor_id).parent().find('iframe')[0],
					oIframeWindow = oIframe.contentWindow,
					oIframeBody = $('#' + oEmoji.opts.editor_id).parent().find('iframe').contents().find('body')[0];

				oEmoji.attachAtWho(oEmoji, $(oIframeBody), oIframeWindow);
			}
		};
	};
})(jQuery, window, document);
