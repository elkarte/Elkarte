/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

var disableDrafts = false,
	emoji_url ='';

(function ($, window, document)
{
	'use strict';

	// Editor instance
	var editor,
		rangeHelper;

	// These are all we know, but we can only remember about 6 of them
	var emojies = [
		{name: 'joy', key: '1f602'},
		{name: 'heart', key: '2764'},
		{name: 'heart_eyes', key: '1f60d'},
		{name: 'sob', key: '1f62d'},
		{name: 'blush', key: '1f60a'},
		{name: 'unamused', key: '1f612'},
		{name: 'kissing_heart', key: '1f618'},
		{name: 'two_hearts', key: '1f495'},
		{name: 'weary', key: '1f629'},
		{name: 'ok_hand', key: '1f44c'},
		{name: 'pensive', key: '1f614'},
		{name: 'smirk', key: '1f60f'},
		{name: 'grin', key: '1f601'},
		{name: 'recycle', key: '267b'},
		{name: 'wink', key: '1f609'},
		{name: 'thumbsup', key: '1f44d'},
		{name: 'pray', key: '1f64f'},
		{name: 'relieved', key: '1f60c'},
		{name: 'notes', key: '1f3b6'},
		{name: 'flushed', key: '1f633'},
		{name: 'raised_hands', key: '1f64c'},
		{name: 'see_no_evil', key: '1f648'},
		{name: 'cry', key: '1f622'},
		{name: 'sunglasses', key: '1f60e'},
		{name: 'v', key: '270c'},
		{name: 'eyes', key: '1f440'},
		{name: 'sweat_smile', key: '1f605'},
		{name: 'sparkles', key: '2728'},
		{name: 'sleeping', key: '1f634'},
		{name: 'smile', key: '1f604'},
		{name: 'purple_heart', key: '1f49c'},
		{name: 'broken_heart', key: '1f494'},
		{name: 'hundred_points', key: '1f4af'},
		{name: 'expressionless', key: '1f611'},
		{name: 'sparkling_heart', key: '1f496'},
		{name: 'blue_heart', key: '1f499'},
		{name: 'confused', key: '1f615'},
		{name: 'information_desk_person', key: '1f481'},
		{name: 'stuck_out_tongue_winking_eye', key: '1f61c'},
		{name: 'disappointed', key: '1f61e'},
		{name: 'yum', key: '1f60b'},
		{name: 'neutral_face', key: '1f610'},
		{name: 'sleepy', key: '1f62a'},
		{name: 'clap', key: '1f44f'},
		{name: 'cupid', key: '1f498'},
		{name: 'heartpulse', key: '1f497'},
		{name: 'revolving_hearts', key: '1f49e'},
		{name: 'arrow_left', key: '2b05'},
		{name: 'speak_no_evil', key: '1f64a'},
		{name: 'raised_hand', key: '270b'},
		{name: 'kiss', key: '1f48b'},
		{name: 'point_right', key: '1f449'},
		{name: 'cherry_blossom', key: '1f338'},
		{name: 'scream', key: '1f631'},
		{name: 'fire', key: '1f525'},
		{name: 'rage', key: '1f621'},
		{name: 'smiley', key: '1f603'},
		{name: 'tada', key: '1f389'},
		{name: 'oncoming_fist', key: '1f44a'},
		{name: 'tired_face', key: '1f62b'},
		{name: 'camera', key: '1f4f7'},
		{name: 'rose', key: '1f339'},
		{name: 'stuck_out_tongue_closed_eyes', key: '1f61d'},
		{name: 'muscle', key: '1f4aa'},
		{name: 'skull', key: '1f480'},
		{name: 'sunny', key: '2600'},
		{name: 'yellow_heart', key: '1f49b'},
		{name: 'triumph', key: '1f624'},
		{name: 'new_moon_with_face', key: '1f31a'},
		{name: 'laughing', key: '1f606'},
		{name: 'sweat', key: '1f613'},
		{name: 'point_left', key: '1f448'},
		{name: 'heavy_check_mark', key: '2714'},
		{name: 'heart_eyes_cat', key: '1f63b'},
		{name: 'grinning', key: '1f600'},
		{name: 'mask', key: '1f637'},
		{name: 'green_heart', key: '1f49a'},
		{name: 'wave', key: '1f44b'},
		{name: 'persevere', key: '1f623'},
		{name: 'heartbeat', key: '1f493'},
		{name: 'arrow_forward', key: '25b6'},
		{name: 'arrow_backward', key: '25c0'},
		{name: 'arrow_right_hook', key: '21aa'},
		{name: 'leftwards_arrow_with_hook', key: '21a9'},
		{name: 'crown', key: '1f451'},
		{name: 'kissing_closed_eyes', key: '1f61a'},
		{name: 'stuck_out_tongue', key: '1f61b'},
		{name: 'disappointed_relieved', key: '1f625'},
		{name: 'innocent', key: '1f607'},
		{name: 'headphones', key: '1f3a7'},
		{name: 'white_check_mark', key: '2705'},
		{name: 'confounded', key: '1f616'},
		{name: 'arrow_right', key: '27a1'},
		{name: 'angry', key: '1f620'},
		{name: 'grimacing', key: '1f62c'},
		{name: 'star2', key: '1f31f'},
		{name: 'gun', key: '1f52b'},
		{name: 'raising_hand', key: '1f64b'},
		{name: 'thumbsdown', key: '1f44e'},
		{name: 'dancer', key: '1f483'},
		{name: 'musical_note', key: '1f3b5'},
		{name: 'no_mouth', key: '1f636'},
		{name: 'dizzy', key: '1f4ab'},
		{name: 'fist', key: '270a'},
		{name: 'point_down', key: '1f447'},
		{name: 'red_circle', key: '1f534'},
		{name: 'no_good', key: '1f645'},
		{name: 'boom', key: '1f4a5'},
		{name: 'thought_balloon', key: '1f4ad'},
		{name: 'tongue', key: '1f445'},
		{name: 'poop', key: '1f4a9'},
		{name: 'cold_sweat', key: '1f630'},
		{name: 'gem', key: '1f48e'},
		{name: 'ok_woman', key: '1f646'},
		{name: 'pizza', key: '1f355'},
		{name: 'joy_cat', key: '1f639'},
		{name: 'sun_with_face', key: '1f31e'},
		{name: 'leaves', key: '1f343'},
		{name: 'sweat_drops', key: '1f4a6'},
		{name: 'penguin', key: '1f427'},
		{name: 'zzz', key: '1f4a4'},
		{name: 'walking', key: '1f6b6'},
		{name: 'airplane', key: '2708'},
		{name: 'balloon', key: '1f388'},
		{name: 'star', key: '2b50'},
		{name: 'ribbon', key: '1f380'},
		{name: 'ballot_box_with_check', key: '2611'},
		{name: 'worried', key: '1f61f'},
		{name: 'underage', key: '1f51e'},
		{name: 'fearful', key: '1f628'},
		{name: 'four_leaf_clover', key: '1f340'},
		{name: 'hibiscus', key: '1f33a'},
		{name: 'microphone', key: '1f3a4'},
		{name: 'open_hands', key: '1f450'},
		{name: 'ghost', key: '1f47b'},
		{name: 'palm_tree', key: '1f334'},
		{name: 'bangbang', key: '203c'},
		{name: 'nail_care', key: '1f485'},
		{name: 'x', key: '274c'},
		{name: 'alien', key: '1f47d'},
		{name: 'bow', key: '1f647'},
		{name: 'cloud', key: '2601'},
		{name: 'soccer', key: '26bd'},
		{name: 'angel', key: '1f47c'},
		{name: 'dancers', key: '1f46f'},
		{name: 'exclamation', key: '2757'},
		{name: 'snowflake', key: '2744'},
		{name: 'point_up', key: '261d'},
		{name: 'kissing_smiling_eyes', key: '1f619'},
		{name: 'rainbow', key: '1f308'},
		{name: 'crescent_moon', key: '1f319'},
		{name: 'heart_decoration', key: '1f49f'},
		{name: 'gift_heart', key: '1f49d'},
		{name: 'gift', key: '1f381'},
		{name: 'beers', key: '1f37b'},
		{name: 'anguished', key: '1f627'},
		{name: 'earth_africa', key: '1f30d'},
		{name: 'movie_camera', key: '1f3a5'},
		{name: 'anchor', key: '2693'},
		{name: 'zap', key: '26a1'},
		{name: 'heavy_multiplication_x', key: '2716'},
		{name: 'runner', key: '1f3c3'},
		{name: 'sunflower', key: '1f33b'},
		{name: 'earth_americas', key: '1f30e'},
		{name: 'bouquet', key: '1f490'},
		{name: 'dog', key: '1f436'},
		{name: 'moneybag', key: '1f4b0'},
		{name: 'herb', key: '1f33f'},
		{name: 'couple', key: '1f46b'},
		{name: 'fallen_leaf', key: '1f342'},
		{name: 'tulip', key: '1f337'},
		{name: 'birthday', key: '1f382'},
		{name: 'cat', key: '1f431'},
		{name: 'coffee', key: '2615'},
		{name: 'dizzy_face', key: '1f635'},
		{name: 'point_up_2', key: '1f446'},
		{name: 'open_mouth', key: '1f62e'},
		{name: 'hushed', key: '1f62f'},
		{name: 'basketball', key: '1f3c0'},
		{name: 'christmas_tree', key: '1f384'},
		{name: 'ring', key: '1f48d'},
		{name: 'full_moon_with_face', key: '1f31d'},
		{name: 'astonished', key: '1f632'},
		{name: 'two_women_holding_hands', key: '1f46d'},
		{name: 'money_with_wings', key: '1f4b8'},
		{name: 'crying_cat_face', key: '1f63f'},
		{name: 'hear_no_evil', key: '1f649'},
		{name: 'dash', key: '1f4a8'},
		{name: 'cactus', key: '1f335'},
		{name: 'hotsprings', key: '2668'},
		{name: 'telephone', key: '260e'},
		{name: 'maple_leaf', key: '1f341'},
		{name: 'princess', key: '1f478'},
		{name: 'massage', key: '1f486'},
		{name: 'love_letter', key: '1f48c'},
		{name: 'trophy', key: '1f3c6'},
		{name: 'person_frowning', key: '1f64d'},
		{name: 'us', key: '1f1fa'},
		{name: 'confetti_ball', key: '1f38a'},
		{name: 'blossom', key: '1f33c'},
		{name: 'kitchen_knife', key: '1f52a'},
		{name: 'lips', key: '1f444'},
		{name: 'fries', key: '1f35f'},
		{name: 'doughnut', key: '1f369'},
		{name: 'frowning', key: '1f626'},
		{name: 'ocean', key: '1f30a'},
		{name: 'bomb', key: '1f4a3'},
		{name: 'ok', key: '1f197'},
		{name: 'cyclone', key: '1f300'},
		{name: 'rocket', key: '1f680'},
		{name: 'umbrella', key: '2614'},
		{name: 'couplekiss', key: '1f48f'},
		{name: 'couple_with_heart', key: '1f491'},
		{name: 'lollipop', key: '1f36d'},
		{name: 'clapper', key: '1f3ac'},
		{name: 'pig', key: '1f437'},
		{name: 'smiling_imp', key: '1f608'},
		{name: 'imp', key: '1f47f'},
		{name: 'bee', key: '1f41d'},
		{name: 'kissing_cat', key: '1f63d'},
		{name: 'anger', key: '1f4a2'},
		{name: 'musical_score', key: '1f3bc'},
		{name: 'santa', key: '1f385'},
		{name: 'earth_asia', key: '1f30f'},
		{name: 'football', key: '1f3c8'},
		{name: 'guitar', key: '1f3b8'},
		{name: 'panda_face', key: '1f43c'},
		{name: 'speech_balloon', key: '1f4ac'},
		{name: 'strawberry', key: '1f353'},
		{name: 'smirk_cat', key: '1f63c'},
		{name: 'banana', key: '1f34c'},
		{name: 'watermelon', key: '1f349'},
		{name: 'snowman', key: '26c4'},
		{name: 'smile_cat', key: '1f638'},
		{name: 'top', key: '1f51d'},
		{name: 'eggplant', key: '1f346'},
		{name: 'crystal_ball', key: '1f52e'},
		{name: 'fork_and_knife', key: '1f374'},
		{name: 'calling', key: '1f4f2'},
		{name: 'iphone', key: '1f4f1'},
		{name: 'partly_sunny', key: '26c5'},
		{name: 'warning', key: '26a0'},
		{name: 'scream_cat', key: '1f640'},
		{name: 'small_orange_diamond', key: '1f538'},
		{name: 'baby', key: '1f476'},
		{name: 'feet', key: '1f43e'},
		{name: 'footprints', key: '1f463'},
		{name: 'beer', key: '1f37a'},
		{name: 'wine_glass', key: '1f377'},
		{name: 'o', key: '2b55'},
		{name: 'video_camera', key: '1f4f9'},
		{name: 'rabbit', key: '1f430'},
		{name: 'tropical_drink', key: '1f379'},
		{name: 'smoking', key: '1f6ac'},
		{name: 'space_invader', key: '1f47e'},
		{name: 'peach', key: '1f351'},
		{name: 'snake', key: '1f40d'},
		{name: 'turtle', key: '1f422'},
		{name: 'cherries', key: '1f352'},
		{name: 'kissing', key: '1f617'},
		{name: 'frog', key: '1f438'},
		{name: 'milky_way', key: '1f30c'},
		{name: 'rotating_light', key: '1f6a8'},
		{name: 'hatching_chick', key: '1f423'},
		{name: 'closed_book', key: '1f4d5'},
		{name: 'candy', key: '1f36c'},
		{name: 'hamburger', key: '1f354'},
		{name: 'bear', key: '1f43b'},
		{name: 'tiger', key: '1f42f'},
		{name: 'automobile', key: '1f697'},
		{name: 'icecream', key: '1f366'},
		{name: 'pineapple', key: '1f34d'},
		{name: 'ear_of_rice', key: '1f33e'},
		{name: 'syringe', key: '1f489'},
		{name: 'put_litter_in_its_place', key: '1f6ae'},
		{name: 'chocolate_bar', key: '1f36b'},
		{name: 'black_small_square', key: '25aa'},
		{name: 'tv', key: '1f4fa'},
		{name: 'pill', key: '1f48a'},
		{name: 'octopus', key: '1f419'},
		{name: 'jack_o_lantern', key: '1f383'},
		{name: 'grapes', key: '1f347'},
		{name: 'smiley_cat', key: '1f63a'},
		{name: 'cd', key: '1f4bf'},
		{name: 'cocktail', key: '1f378'},
		{name: 'cake', key: '1f370'},
		{name: 'video_game', key: '1f3ae'},
		{name: 'arrow_down', key: '2b07'},
		{name: 'no_entry_sign', key: '1f6ab'},
		{name: 'lipstick', key: '1f484'},
		{name: 'whale', key: '1f433'},
		{name: 'memo', key: '1f4dd'},
		{name: 'cookie', key: '1f36a'},
		{name: 'dolphin', key: '1f42c'},
		{name: 'loud_sound', key: '1f50a'},
		{name: 'man', key: '1f468'},
		{name: 'hatched_chick', key: '1f425'},
		{name: 'monkey', key: '1f412'},
		{name: 'books', key: '1f4da'},
		{name: 'japanese_ogre', key: '1f479'},
		{name: 'guardsman', key: '1f482'},
		{name: 'loudspeaker', key: '1f4e2'},
		{name: 'scissors', key: '2702'},
		{name: 'girl', key: '1f467'},
		{name: 'mortar_board', key: '1f393'},
		{name: 'fr', key: '1f1eb'},
		{name: 'baseball', key: '26be'},
		{name: 'vertical_traffic_light', key: '1f6a6'},
		{name: 'woman', key: '1f469'},
		{name: 'fireworks', key: '1f386'},
		{name: 'stars', key: '1f320'},
		{name: 'sos', key: '1f198'},
		{name: 'mushroom', key: '1f344'},
		{name: 'pouting_cat', key: '1f63e'},
		{name: 'left_luggage', key: '1f6c5'},
		{name: 'high_heel', key: '1f460'},
		{name: 'dart', key: '1f3af'},
		{name: 'swimmer', key: '1f3ca'},
		{name: 'key', key: '1f511'},
		{name: 'bikini', key: '1f459'},
		{name: 'family', key: '1f46a'},
		{name: 'pencil2', key: '270f'},
		{name: 'elephant', key: '1f418'},
		{name: 'droplet', key: '1f4a7'},
		{name: 'seedling', key: '1f331'},
		{name: 'apple', key: '1f34e'},
		{name: 'cool', key: '1f192'},
		{name: 'telephone_receiver', key: '1f4de'},
		{name: 'dollar', key: '1f4b5'},
		{name: 'house_with_garden', key: '1f3e1'},
		{name: 'book', key: '1f4d6'},
		{name: 'haircut', key: '1f487'},
		{name: 'computer', key: '1f4bb'},
		{name: 'bulb', key: '1f4a1'},
		{name: 'question', key: '2753'},
		{name: 'back', key: '1f519'},
		{name: 'boy', key: '1f466'},
		{name: 'closed_lock_with_key', key: '1f510'},
		{name: 'person_with_pouting_face', key: '1f64e'},
		{name: 'tangerine', key: '1f34a'},
		{name: 'sunrise', key: '1f305'},
		{name: 'poultry_leg', key: '1f357'},
		{name: 'blue_circle', key: '1f535'},
		{name: 'oncoming_automobile', key: '1f698'},
		{name: 'shaved_ice', key: '1f367'},
		{name: 'bird', key: '1f426'},
		{name: 'first_quarter_moon_with_face', key: '1f31b'},
		{name: 'eyeglasses', key: '1f453'},
		{name: 'goat', key: '1f410'},
		{name: 'night_with_stars', key: '1f303'},
		{name: 'older_woman', key: '1f475'},
		{name: 'black_circle', key: '26ab'},
		{name: 'new_moon', key: '1f311'},
		{name: 'two_men_holding_hands', key: '1f46c'},
		{name: 'white_circle', key: '26aa'},
		{name: 'customs', key: '1f6c3'},
		{name: 'tropical_fish', key: '1f420'},
		{name: 'house', key: '1f3e0'},
		{name: 'arrows_clockwise', key: '1f503'},
		{name: 'last_quarter_moon_with_face', key: '1f31c'},
		{name: 'round_pushpin', key: '1f4cd'},
		{name: 'full_moon', key: '1f315'},
		{name: 'athletic_shoe', key: '1f45f'},
		{name: 'lemon', key: '1f34b'},
		{name: 'baby_bottle', key: '1f37c'},
		{name: 'artist_palette', key: '1f3a8'},
		{name: 'spaghetti', key: '1f35d'},
		{name: 'wind_chime', key: '1f390'},
		{name: 'fish_cake', key: '1f365'},
		{name: 'evergreen_tree', key: '1f332'},
		{name: 'up', key: '1f199'},
		{name: 'arrow_up', key: '2b06'},
		{name: 'arrow_upper_right', key: '2197'},
		{name: 'arrow_lower_right', key: '2198'},
		{name: 'arrow_lower_left', key: '2199'},
		{name: 'performing_arts', key: '1f3ad'},
		{name: 'nose', key: '1f443'},
		{name: 'pig_nose', key: '1f43d'},
		{name: 'fish', key: '1f41f'},
		{name: 'man_with_turban', key: '1f473'},
		{name: 'koala', key: '1f428'},
		{name: 'ear', key: '1f442'},
		{name: 'eight_spoked_asterisk', key: '2733'},
		{name: 'small_blue_diamond', key: '1f539'},
		{name: 'shower', key: '1f6bf'},
		{name: 'bug', key: '1f41b'},
		{name: 'ramen', key: '1f35c'},
		{name: 'tophat', key: '1f3a9'},
		{name: 'bride_with_veil', key: '1f470'},
		{name: 'fuelpump', key: '26fd'},
		{name: 'checkered_flag', key: '1f3c1'},
		{name: 'horse', key: '1f434'},
		{name: 'watch', key: '231a'},
		{name: 'monkey_face', key: '1f435'},
		{name: 'baby_symbol', key: '1f6bc'},
		{name: 'new', key: '1f195'},
		{name: 'free', key: '1f193'},
		{name: 'sparkler', key: '1f387'},
		{name: 'corn', key: '1f33d'},
		{name: 'tennis', key: '1f3be'},
		{name: 'alarm_clock', key: '23f0'},
		{name: 'battery', key: '1f50b'},
		{name: 'grey_exclamation', key: '2755'},
		{name: 'wolf', key: '1f43a'},
		{name: 'moyai', key: '1f5ff'},
		{name: 'cow', key: '1f42e'},
		{name: 'mega', key: '1f4e3'},
		{name: 'older_man', key: '1f474'},
		{name: 'dress', key: '1f457'},
		{name: 'link', key: '1f517'},
		{name: 'chicken', key: '1f414'},
		{name: 'cooking', key: '1f373'},
		{name: 'whale2', key: '1f40b'},
		{name: 'arrow_upper_left', key: '2196'},
		{name: 'deciduous_tree', key: '1f333'},
		{name: 'bento', key: '1f371'},
		{name: 'pushpin', key: '1f4cc'},
		{name: 'soon', key: '1f51c'},
		{name: 'repeat', key: '1f501'},
		{name: 'dragon', key: '1f409'},
		{name: 'hamster', key: '1f439'},
		{name: 'golf', key: '26f3'},
		{name: 'surfer', key: '1f3c4'},
		{name: 'mouse', key: '1f42d'},
		{name: 'waxing_crescent_moon', key: '1f312'},
		{name: 'blue_car', key: '1f699'},
		{name: 'a', key: '1f170'},
		{name: 'interrobang', key: '2049'},
		{name: 'u5272', key: '1f239'},
		{name: 'electric_plug', key: '1f50c'},
		{name: 'first_quarter_moon', key: '1f313'},
		{name: 'cancer', key: '264b'},
		{name: 'trident', key: '1f531'},
		{name: 'bread', key: '1f35e'},
		{name: 'cop', key: '1f46e'},
		{name: 'tea', key: '1f375'},
		{name: 'fishing_pole_and_fish', key: '1f3a3'},
		{name: 'waxing_gibbous_moon', key: '1f314'},
		{name: 'bike', key: '1f6b2'},
		{name: 'bust_in_silhouette', key: '1f464'},
		{name: 'rice', key: '1f35a'},
		{name: 'radio', key: '1f4fb'},
		{name: 'baby_chick', key: '1f424'},
		{name: 'arrow_heading_down', key: '2935'},
		{name: 'waning_crescent_moon', key: '1f318'},
		{name: 'arrow_up_down', key: '2195'},
		{name: 'last_quarter_moon', key: '1f317'},
		{name: 'radio_button', key: '1f518'},
		{name: 'sheep', key: '1f411'},
		{name: 'person_with_blond_hair', key: '1f471'},
		{name: 'waning_gibbous_moon', key: '1f316'},
		{name: 'lock', key: '1f512'},
		{name: 'green_apple', key: '1f34f'},
		{name: 'japanese_goblin', key: '1f47a'},
		{name: 'curly_loop', key: '27b0'},
		{name: 'triangular_flag_on_post', key: '1f6a9'},
		{name: 'arrows_counterclockwise', key: '1f504'},
		{name: 'racehorse', key: '1f40e'},
		{name: 'fried_shrimp', key: '1f364'},
		{name: 'sunrise_over_mountains', key: '1f304'},
		{name: 'volcano', key: '1f30b'},
		{name: 'rooster', key: '1f413'},
		{name: 'inbox_tray', key: '1f4e5'},
		{name: 'wedding', key: '1f492'},
		{name: 'sushi', key: '1f363'},
		{name: 'wavy_dash', key: '3030'},
		{name: 'ice_cream', key: '1f368'},
		{name: 'rewind', key: '23ea'},
		{name: 'tomato', key: '1f345'},
		{name: 'rabbit2', key: '1f407'},
		{name: 'eight_pointed_black_star', key: '2734'},
		{name: 'small_red_triangle', key: '1f53a'},
		{name: 'high_brightness', key: '1f506'},
		{name: 'heavy_plus_sign', key: '2795'},
		{name: 'man_with_gua_pi_mao', key: '1f472'},
		{name: 'convenience_store', key: '1f3ea'},
		{name: 'busts_in_silhouette', key: '1f465'},
		{name: 'beetle', key: '1f41e'},
		{name: 'small_red_triangle_down', key: '1f53b'},
		{name: 'arrow_heading_up', key: '2934'},
		{name: 'name_badge', key: '1f4db'},
		{name: 'bath', key: '1f6c0'},
		{name: 'no_entry', key: '26d4'},
		{name: 'crocodile', key: '1f40a'},
		{name: 'chestnut', key: '1f330'},
		{name: 'dog2', key: '1f415'},
		{name: 'cat2', key: '1f408'},
		{name: 'hammer', key: '1f528'},
		{name: 'meat_on_bone', key: '1f356'},
		{name: 'shell', key: '1f41a'},
		{name: 'sparkle', key: '2747'},
		{name: 'sailboat', key: '26f5'},
		{name: 'b', key: '1f171'},
		{name: 'm', key: '24c2'},
		{name: 'poodle', key: '1f429'},
		{name: 'aquarius', key: '2652'},
		{name: 'stew', key: '1f372'},
		{name: 'jeans', key: '1f456'},
		{name: 'honey_pot', key: '1f36f'},
		{name: 'musical_keyboard', key: '1f3b9'},
		{name: 'unlock', key: '1f513'},
		{name: 'black_nib', key: '2712'},
		{name: 'statue_of_liberty', key: '1f5fd'},
		{name: 'heavy_dollar_sign', key: '1f4b2'},
		{name: 'snowboarder', key: '1f3c2'},
		{name: 'white_flower', key: '1f4ae'},
		{name: 'necktie', key: '1f454'},
		{name: 'diamond_shape_with_a_dot_inside', key: '1f4a0'},
		{name: 'aries', key: '2648'},
		{name: 'womens', key: '1f6ba'},
		{name: 'ant', key: '1f41c'},
		{name: 'scorpius', key: '264f'},
		{name: 'city_sunset', key: '1f307'},
		{name: 'hourglass_flowing_sand', key: '23f3'},
		{name: 'o2', key: '1f17e'},
		{name: 'dragon_face', key: '1f432'},
		{name: 'snail', key: '1f40c'},
		{name: 'dvd', key: '1f4c0'},
		{name: 'shirt', key: '1f455'},
		{name: 'game_die', key: '1f3b2'},
		{name: 'heavy_minus_sign', key: '2796'},
		{name: 'dolls', key: '1f38e'},
		{name: 'sagittarius', key: '2650'},
		{name: '8ball', key: '1f3b1'},
		{name: 'bus', key: '1f68c'},
		{name: 'custard', key: '1f36e'},
		{name: 'crossed_flags', key: '1f38c'},
		{name: 'part_alternation_mark', key: '303d'},
		{name: 'camel', key: '1f42b'},
		{name: 'curry', key: '1f35b'},
		{name: 'steam_locomotive', key: '1f682'},
		{name: 'hospital', key: '1f3e5'},
		{name: 'large_blue_diamond', key: '1f537'},
		{name: 'tanabata_tree', key: '1f38b'},
		{name: 'bell', key: '1f514'},
		{name: 'leo', key: '264c'},
		{name: 'gemini', key: '264a'},
		{name: 'pear', key: '1f350'},
		{name: 'large_orange_diamond', key: '1f536'},
		{name: 'taurus', key: '2649'},
		{name: 'globe_with_meridians', key: '1f310'},
		{name: 'door', key: '1f6aa'},
		{name: 'clock6', key: '1f555'},
		{name: 'oncoming_police_car', key: '1f694'},
		{name: 'envelope_with_arrow', key: '1f4e9'},
		{name: 'closed_umbrella', key: '1f302'},
		{name: 'saxophone', key: '1f3b7'},
		{name: 'church', key: '26ea'},
		{name: 'bicyclist', key: '1f6b4'},
		{name: 'pisces', key: '2653'},
		{name: 'dango', key: '1f361'},
		{name: 'capricorn', key: '2651'},
		{name: 'office', key: '1f3e2'},
		{name: 'rowboat', key: '1f6a3'},
		{name: 'womans_hat', key: '1f452'},
		{name: 'mans_shoe', key: '1f45e'},
		{name: 'love_hotel', key: '1f3e9'},
		{name: 'mount_fuji', key: '1f5fb'},
		{name: 'dromedary_camel', key: '1f42a'},
		{name: 'handbag', key: '1f45c'},
		{name: 'hourglass', key: '231b'},
		{name: 'negative_squared_cross_mark', key: '274e'},
		{name: 'trumpet', key: '1f3ba'},
		{name: 'school', key: '1f3eb'},
		{name: 'cow2', key: '1f404'},
		{name: 'cityscape_at_dusk', key: '1f306'},
		{name: 'construction_worker', key: '1f477'},
		{name: 'toilet', key: '1f6bd'},
		{name: 'pig2', key: '1f416'},
		{name: 'grey_question', key: '2754'},
		{name: 'beginner', key: '1f530'},
		{name: 'violin', key: '1f3bb'},
		{name: 'on', key: '1f51b'},
		{name: 'credit_card', key: '1f4b3'},
		{name: 'id', key: '1f194'},
		{name: 'secret', key: '3299'},
		{name: 'ferris_wheel', key: '1f3a1'},
		{name: 'bowling', key: '1f3b3'},
		{name: 'libra', key: '264e'},
		{name: 'virgo', key: '264d'},
		{name: 'barber', key: '1f488'},
		{name: 'purse', key: '1f45b'},
		{name: 'roller_coaster', key: '1f3a2'},
		{name: 'rat', key: '1f400'},
		{name: 'date', key: '1f4c5'},
		{name: 'rugby_football', key: '1f3c9'},
		{name: 'ram', key: '1f40f'},
		{name: 'arrow_up_small', key: '1f53c'},
		{name: 'black_square_button', key: '1f532'},
		{name: 'mobile_phone_off', key: '1f4f4'},
		{name: 'tokyo_tower', key: '1f5fc'},
		{name: 'congratulations', key: '3297'},
		{name: 'kimono', key: '1f458'},
		{name: 'ship', key: '1f6a2'},
		{name: 'mag_right', key: '1f50e'},
		{name: 'mag', key: '1f50d'},
		{name: 'fire_engine', key: '1f692'},
		{name: 'clock1130', key: '1f566'},
		{name: 'police_car', key: '1f693'},
		{name: 'black_joker', key: '1f0cf'},
		{name: 'bridge_at_night', key: '1f309'},
		{name: 'package', key: '1f4e6'},
		{name: 'oncoming_taxi', key: '1f696'},
		{name: 'calendar', key: '1f4c6'},
		{name: 'horse_racing', key: '1f3c7'},
		{name: 'tiger2', key: '1f405'},
		{name: 'boot', key: '1f462'},
		{name: 'ambulance', key: '1f691'},
		{name: 'white_square_button', key: '1f533'},
		{name: 'boar', key: '1f417'},
		{name: 'school_satchel', key: '1f392'},
		{name: 'loop', key: '27bf'},
		{name: 'pound', key: '1f4b7'},
		{name: 'information_source', key: '2139'},
		{name: 'ox', key: '1f402'},
		{name: 'rice_ball', key: '1f359'},
		{name: 'vs', key: '1f19a'},
		{name: 'end', key: '1f51a'},
		{name: 'parking', key: '1f17f'},
		{name: 'sandal', key: '1f461'},
		{name: 'tent', key: '26fa'},
		{name: 'seat', key: '1f4ba'},
		{name: 'taxi', key: '1f695'},
		{name: 'black_medium_small_square', key: '25fe'},
		{name: 'briefcase', key: '1f4bc'},
		{name: 'newspaper', key: '1f4f0'},
		{name: 'circus_tent', key: '1f3aa'},
		{name: 'six_pointed_star', key: '1f52f'},
		{name: 'mens', key: '1f6b9'},
		{name: 'european_castle', key: '1f3f0'},
		{name: 'flashlight', key: '1f526'},
		{name: 'foggy', key: '1f301'},
		{name: 'arrow_double_up', key: '23eb'},
		{name: 'bamboo', key: '1f38d'},
		{name: 'ticket', key: '1f3ab'},
		{name: 'helicopter', key: '1f681'},
		{name: 'minidisc', key: '1f4bd'},
		{name: 'oncoming_bus', key: '1f68d'},
		{name: 'melon', key: '1f348'},
		{name: 'white_small_square', key: '25ab'},
		{name: 'european_post_office', key: '1f3e4'},
		{name: 'keycap_ten', key: '1f51f'},
		{name: 'notebook', key: '1f4d3'},
		{name: 'no_bell', key: '1f515'},
		{name: 'oden', key: '1f362'},
		{name: 'flags', key: '1f38f'},
		{name: 'carousel_horse', key: '1f3a0'},
		{name: 'blowfish', key: '1f421'},
		{name: 'chart_with_upwards_trend', key: '1f4c8'},
		{name: 'sweet_potato', key: '1f360'},
		{name: 'ski', key: '1f3bf'},
		{name: 'clock12', key: '1f55b'},
		{name: 'signal_strength', key: '1f4f6'},
		{name: 'construction', key: '1f6a7'},
		{name: 'black_medium_square', key: '25fc'},
		{name: 'satellite', key: '1f4e1'},
		{name: 'euro', key: '1f4b6'},
		{name: 'womans_clothes', key: '1f45a'},
		{name: 'ledger', key: '1f4d2'},
		{name: 'leopard', key: '1f406'},
		{name: 'low_brightness', key: '1f505'},
		{name: 'clock3', key: '1f552'},
		{name: 'department_store', key: '1f3ec'},
		{name: 'truck', key: '1f69a'},
		{name: 'sake', key: '1f376'},
		{name: 'railway_car', key: '1f683'},
		{name: 'speedboat', key: '1f6a4'},
		{name: 'vhs', key: '1f4fc'},
		{name: 'clock1', key: '1f550'},
		{name: 'arrow_double_down', key: '23ec'},
		{name: 'water_buffalo', key: '1f403'},
		{name: 'arrow_down_small', key: '1f53d'},
		{name: 'yen', key: '1f4b4'},
		{name: 'mute', key: '1f507'},
		{name: 'running_shirt_with_sash', key: '1f3bd'},
		{name: 'white_large_square', key: '2b1c'},
		{name: 'wheelchair', key: '267f'},
		{name: 'clock2', key: '1f551'},
		{name: 'paperclip', key: '1f4ce'},
		{name: 'atm', key: '1f3e7'},
		{name: 'cinema', key: '1f3a6'},
		{name: 'telescope', key: '1f52d'},
		{name: 'rice_scene', key: '1f391'},
		{name: 'blue_book', key: '1f4d8'},
		{name: 'white_medium_square', key: '25fb'},
		{name: 'postbox', key: '1f4ee'},
		{name: 'e-mail', key: '1f4e7'},
		{name: 'mouse2', key: '1f401'},
		{name: 'bullettrain_side', key: '1f684'},
		{name: 'ideograph_advantage', key: '1f250'},
		{name: 'nut_and_bolt', key: '1f529'},
		{name: 'ng', key: '1f196'},
		{name: 'hotel', key: '1f3e8'},
		{name: 'wc', key: '1f6be'},
		{name: 'izakaya_lantern', key: '1f3ee'},
		{name: 'repeat_one', key: '1f502'},
		{name: 'mailbox_with_mail', key: '1f4ec'},
		{name: 'chart_with_downwards_trend', key: '1f4c9'},
		{name: 'green_book', key: '1f4d7'},
		{name: 'tractor', key: '1f69c'},
		{name: 'fountain', key: '26f2'},
		{name: 'metro', key: '1f687'},
		{name: 'clipboard', key: '1f4cb'},
		{name: 'no_mobile_phones', key: '1f4f5'},
		{name: 'clock4', key: '1f553'},
		{name: 'no_smoking', key: '1f6ad'},
		{name: 'black_large_square', key: '2b1b'},
		{name: 'slot_machine', key: '1f3b0'},
		{name: 'clock5', key: '1f554'},
		{name: 'bathtub', key: '1f6c1'},
		{name: 'scroll', key: '1f4dc'},
		{name: 'station', key: '1f689'},
		{name: 'rice_cracker', key: '1f358'},
		{name: 'bank', key: '1f3e6'},
		{name: 'wrench', key: '1f527'},
		{name: 'u6307', key: '1f22f'},
		{name: 'articulated_lorry', key: '1f69b'},
		{name: 'page_facing_up', key: '1f4c4'},
		{name: 'ophiuchus', key: '26ce'},
		{name: 'bar_chart', key: '1f4ca'},
		{name: 'no_pedestrians', key: '1f6b7'},
		{name: 'vibration_mode', key: '1f4f3'},
		{name: 'clock10', key: '1f559'},
		{name: 'clock9', key: '1f558'},
		{name: 'bullettrain_front', key: '1f685'},
		{name: 'minibus', key: '1f690'},
		{name: 'tram', key: '1f68a'},
		{name: 'clock8', key: '1f557'},
		{name: 'u7a7a', key: '1f233'},
		{name: 'traffic_light', key: '1f6a5'},
		{name: 'mountain_bicyclist', key: '1f6b5'},
		{name: 'microscope', key: '1f52c'},
		{name: 'japanese_castle', key: '1f3ef'},
		{name: 'bookmark', key: '1f516'},
		{name: 'bookmark_tabs', key: '1f4d1'},
		{name: 'pouch', key: '1f45d'},
		{name: 'ab', key: '1f18e'},
		{name: 'page_with_curl', key: '1f4c3'},
		{name: 'flower_playing_cards', key: '1f3b4'},
		{name: 'clock11', key: '1f55a'},
		{name: 'fax', key: '1f4e0'},
		{name: 'clock7', key: '1f556'},
		{name: 'white_medium_small_square', key: '25fd'},
		{name: 'currency_exchange', key: '1f4b1'},
		{name: 'sound', key: '1f509'},
		{name: 'chart', key: '1f4b9'},
		{name: 'cl', key: '1f191'},
		{name: 'floppy_disk', key: '1f4be'},
		{name: 'post_office', key: '1f3e3'},
		{name: 'speaker', key: '1f508'},
		{name: 'japan', key: '1f5fe'},
		{name: 'u55b6', key: '1f23a'},
		{name: 'mahjong', key: '1f004'},
		{name: 'incoming_envelope', key: '1f4e8'},
		{name: 'orange_book', key: '1f4d9'},
		{name: 'restroom', key: '1f6bb'},
		{name: 'u7121', key: '1f21a'},
		{name: 'u6709', key: '1f236'},
		{name: 'triangular_ruler', key: '1f4d0'},
		{name: 'train', key: '1f68b'},
		{name: 'u7533', key: '1f238'},
		{name: 'trolleybus', key: '1f68e'},
		{name: 'u6708', key: '1f237'},
		{name: 'input_numbers', key: '1f522'},
		{name: 'notebook_with_decorative_cover', key: '1f4d4'},
		{name: 'u7981', key: '1f232'},
		{name: 'u6e80', key: '1f235'},
		{name: 'postal_horn', key: '1f4ef'},
		{name: 'factory', key: '1f3ed'},
		{name: 'children_crossing', key: '1f6b8'},
		{name: 'train2', key: '1f686'},
		{name: 'straight_ruler', key: '1f4cf'},
		{name: 'pager', key: '1f4df'},
		{name: 'accept', key: '1f251'},
		{name: 'u5408', key: '1f234'},
		{name: 'lock_with_ink_pen', key: '1f50f'},
		{name: 'clock130', key: '1f55c'},
		{name: 'sa', key: '1f202'},
		{name: 'outbox_tray', key: '1f4e4'},
		{name: 'twisted_rightwards_arrows', key: '1f500'},
		{name: 'mailbox', key: '1f4eb'},
		{name: 'light_rail', key: '1f688'},
		{name: 'clock930', key: '1f564'},
		{name: 'busstop', key: '1f68f'},
		{name: 'open_file_folder', key: '1f4c2'},
		{name: 'file_folder', key: '1f4c1'},
		{name: 'potable_water', key: '1f6b0'},
		{name: 'card_index', key: '1f4c7'},
		{name: 'clock230', key: '1f55d'},
		{name: 'monorail', key: '1f69d'},
		{name: 'clock1230', key: '1f567'},
		{name: 'clock1030', key: '1f565'},
		{name: 'abc', key: '1f524'},
		{name: 'mailbox_closed', key: '1f4ea'},
		{name: 'clock430', key: '1f55f'},
		{name: 'mountain_railway', key: '1f69e'},
		{name: 'do_not_litter', key: '1f6af'},
		{name: 'clock330', key: '1f55e'},
		{name: 'heavy_division_sign', key: '2797'},
		{name: 'clock730', key: '1f562'},
		{name: 'clock530', key: '1f560'},
		{name: 'capital_abcd', key: '1f520'},
		{name: 'mailbox_with_no_mail', key: '1f4ed'},
		{name: 'symbols', key: '1f523'},
		{name: 'aerial_tramway', key: '1f6a1'},
		{name: 'clock830', key: '1f563'},
		{name: 'clock630', key: '1f561'},
		{name: 'abcd', key: '1f521'},
		{name: 'mountain_cableway', key: '1f6a0'},
		{name: 'koko', key: '1f201'},
		{name: 'passport_control', key: '1f6c2'},
		{name: 'non-potable_water', key: '1f6b1'},
		{name: 'suspension_railway', key: '1f69f'},
		{name: 'baggage_claim', key: '1f6c4'},
		{name: 'no_bicycles', key: '1f6b3'},
		{name: 'detective', key: '1f575'},
		{name: 'frowning_face', key: '2639'},
		{name: 'skull_crossbones', key: '2620'},
		{name: 'hugging', key: '1f917'},
		{name: 'robot', key: '1f916'},
		{name: 'face_with_headbandage', key: '1f915'},
		{name: 'thinking', key: '1f914'},
		{name: 'nerd', key: '1f913'},
		{name: 'face_with_thermometer', key: '1f912'},
		{name: 'moneymouth_face', key: '1f911'},
		{name: 'zipper_mouth', key: '1f910'},
		{name: 'rolling_eyes', key: '1f644'},
		{name: 'upside_down', key: '1f643'},
		{name: 'slight_smile', key: '1f642'},
		{name: 'slightly_frowning_face', key: '1f641'},
		{name: 'sign_of_the_horns', key: '1f918'},
		{name: 'vulcan_salute', key: '1f596'},
		{name: 'middle_finger', key: '1f595'},
		{name: 'hand_with_fingers_splayed', key: '1f590'},
		{name: 'writing_hand', key: '270d'},
		{name: 'dark_sunglasses', key: '1f576'},
		{name: 'eye', key: '1f441'},
		{name: 'weightlifter', key: '1f3cb'},
		{name: 'basketballer', key: '26f9'},
		{name: 'man_in_suit', key: '1f574'},
		{name: 'golfer', key: '1f3cc'},
		{name: 'heart_exclamation', key: '2763'},
		{name: 'star_of_david', key: '2721'},
		{name: 'cross', key: '271d'},
		{name: 'fleur-de-lis', key: '269c'},
		{name: 'atom', key: '269b'},
		{name: 'wheel_of_dharma', key: '2638'},
		{name: 'yin_yang', key: '262f'},
		{name: 'peace', key: '262e'},
		{name: 'star_and_crescent', key: '262a'},
		{name: 'orthodox_cross', key: '2626'},
		{name: 'biohazard', key: '2623'},
		{name: 'radioactive', key: '2622'},
		{name: 'place_of_worship', key: '1f6d0'},
		{name: 'anger_right', key: '1f5ef'},
		{name: 'menorah', key: '1f54e'},
		{name: 'om_symbol', key: '1f549'},
		{name: 'funeral_urn', key: '26b1'},
		{name: 'coffin', key: '26b0'},
		{name: 'gear', key: '2699'},
		{name: 'alembic', key: '2697'},
		{name: 'scales', key: '2696'},
		{name: 'crossed_swords', key: '2694'},
		{name: 'keyboard', key: '2328'},
		{name: 'oil_drum', key: '1f6e2'},
		{name: 'shield', key: '1f6e1'},
		{name: 'hammer_and_wrench', key: '1f6e0'},
		{name: 'bed', key: '1f6cf'},
		{name: 'bellhop_bell', key: '1f6ce'},
		{name: 'shopping_bags', key: '1f6cd'},
		{name: 'sleeping_accommodation', key: '1f6cc'},
		{name: 'couch_and_lamp', key: '1f6cb'},
		{name: 'ballot_box', key: '1f5f3'},
		{name: 'dagger', key: '1f5e1'},
		{name: 'rolledup_newspaper', key: '1f5de'},
		{name: 'old_key', key: '1f5dd'},
		{name: 'compression', key: '1f5dc'},
		{name: 'spiral_calendar', key: '1f5d3'},
		{name: 'spiral_notepad', key: '1f5d2'},
		{name: 'wastebasket', key: '1f5d1'},
		{name: 'file_cabinet', key: '1f5c4'},
		{name: 'card_file_box', key: '1f5c3'},
		{name: 'card_index_dividers', key: '1f5c2'},
		{name: 'framed_picture', key: '1f5bc'},
		{name: 'trackball', key: '1f5b2'},
		{name: 'computer_mouse', key: '1f5b1'},
		{name: 'printer', key: '1f5a8'},
		{name: 'desktop_computer', key: '1f5a5'},
		{name: 'crayon', key: '1f58d'},
		{name: 'paintbrush', key: '1f58c'},
		{name: 'fountain_pen', key: '1f58b'},
		{name: 'pen', key: '1f58a'},
		{name: 'linked_paperclips', key: '1f587'},
		{name: 'joystick', key: '1f579'},
		{name: 'hole', key: '1f573'},
		{name: 'mantelpiece_clock', key: '1f570'},
		{name: 'candle', key: '1f56f'},
		{name: 'prayer_beads', key: '1f4ff'},
		{name: 'film_projector', key: '1f4fd'},
		{name: 'camera_with_flash', key: '1f4f8'},
		{name: 'amphora', key: '1f3fa'},
		{name: 'label', key: '1f3f7'},
		{name: 'flag_black', key: '1f3f4'},
		{name: 'flag_white', key: '1f3f3'},
		{name: 'film_frames', key: '1f39e'},
		{name: 'control_knobs', key: '1f39b'},
		{name: 'level_slider', key: '1f39a'},
		{name: 'studio_microphone', key: '1f399'},
		{name: 'thermometer', key: '1f321'},
		{name: 'passenger_ship', key: '1f6f3'},
		{name: 'satellite2', key: '1f6f0'},
		{name: 'airplane_arriving', key: '1f6ec'},
		{name: 'airplane_departure', key: '1f6eb'},
		{name: 'small_airplane', key: '1f6e9'},
		{name: 'motor_boat', key: '1f6e5'},
		{name: 'railway_track', key: '1f6e4'},
		{name: 'motorway', key: '1f6e3'},
		{name: 'world_map', key: '1f5fa'},
		{name: 'synagogue', key: '1f54d'},
		{name: 'mosque', key: '1f54c'},
		{name: 'kaaba', key: '1f54b'},
		{name: 'stadium', key: '1f3df'},
		{name: 'national_park', key: '1f3de'},
		{name: 'desert_island', key: '1f3dd'},
		{name: 'desert', key: '1f3dc'},
		{name: 'classical_building', key: '1f3db'},
		{name: 'derelict_house', key: '1f3da'},
		{name: 'cityscape', key: '1f3d9'},
		{name: 'houses', key: '1f3d8'},
		{name: 'building_construction', key: '1f3d7'},
		{name: 'beach_with_umbrella', key: '1f3d6'},
		{name: 'camping', key: '1f3d5'},
		{name: 'snowcapped_mountain', key: '1f3d4'},
		{name: 'racing_car', key: '1f3ce'},
		{name: 'motorcycle', key: '1f3cd'},
		{name: 'bow_and_arrow', key: '1f3f9'},
		{name: 'badminton', key: '1f3f8'},
		{name: 'rosette', key: '1f3f5'},
		{name: 'ping_pong', key: '1f3d3'},
		{name: 'ice_hockey', key: '1f3d2'},
		{name: 'field_hockey', key: '1f3d1'},
		{name: 'volleyball', key: '1f3d0'},
		{name: 'cricket_game', key: '1f3cf'},
		{name: 'medal', key: '1f3c5'},
		{name: 'admission_tickets', key: '1f39f'},
		{name: 'reminder_ribbon', key: '1f397'},
		{name: 'military_medal', key: '1f396'},
		{name: 'cheese_wedge', key: '1f9c0'},
		{name: 'popcorn', key: '1f37f'},
		{name: 'champagne', key: '1f37e'},
		{name: 'fork_and_knife_with_plate', key: '1f37d'},
		{name: 'hot_pepper', key: '1f336'},
		{name: 'burrito', key: '1f32f'},
		{name: 'taco', key: '1f32e'},
		{name: 'hotdog', key: '1f32d'},
		{name: 'shamrock', key: '2618'},
		{name: 'comet', key: '2604'},
		{name: 'unicorn', key: '1f984'},
		{name: 'turkey', key: '1f983'},
		{name: 'scorpion', key: '1f982'},
		{name: 'lion_face', key: '1f981'},
		{name: 'crab', key: '1f980'},
		{name: 'spider_web', key: '1f578'},
		{name: 'spider', key: '1f577'},
		{name: 'dove', key: '1f54a'},
		{name: 'chipmunk', key: '1f43f'},
		{name: 'wind_blowing_face', key: '1f32c'},
		{name: 'fog', key: '1f32b'},
		{name: 'tornado', key: '1f32a'},
		{name: 'cloud_with_lightning', key: '1f329'},
		{name: 'cloud_with_snow', key: '1f328'},
		{name: 'cloud_with_rain', key: '1f327'},
		{name: 'sun_behind_rain_cloud', key: '1f326'},
		{name: 'sun_behind_large_cloud', key: '1f325'},
		{name: 'sun_behind_small_cloud', key: '1f324'},
		{name: 'speaking_head', key: '1f5e3'},
		{name: 'record_button', key: '23fa'},
		{name: 'stop_button', key: '23f9'},
		{name: 'pause_button', key: '23f8'},
		{name: 'play_pause', key: '23ef'},
		{name: 'track_previous', key: '23ee'},
		{name: 'track_next', key: '23ed'},
		{name: 'beach_umbrella', key: '26f1'},
		{name: 'chains', key: '26d3'},
		{name: 'pick', key: '26cf'},
		{name: 'hammer_and_pick', key: '2692'},
		{name: 'timer_clock', key: '23f2'},
		{name: 'stopwatch', key: '23f1'},
		{name: 'ferry', key: '26f4'},
		{name: 'mountain', key: '26f0'},
		{name: 'ice_skate', key: '26f8'},
		{name: 'skier', key: '26f7'},
		{name: 'cloud_with_lightning_and_rain', key: '26c8'},
		{name: 'rescue_worker’s_helmet', key: '26d1'},
		{name: 'black_heart', key: '1f5a4'},
		{name: 'speech_left', key: '1f5e8'},
		{name: 'egg', key: '1f95a'},
		{name: 'octagonal_sign', key: '1f6d1'},
		{name: 'spades', key: '2660'},
		{name: 'hearts', key: '2665'},
		{name: 'diamonds', key: '2666'},
		{name: 'clubs', key: '2663'},
		{name: 'drum', key: '1f941'},
		{name: 'left_right_arrow', key: '2194'},
		{name: 'copyright', key: '00a9'},
		{name: 'registered', key: '00ae'},
		{name: 'tm', key: '2122'},
		{name: 'zero', key: '0030'},
		{name: 'one', key: '0031'},
		{name: 'two', key: '0032'},
		{name: 'three', key: '0033'},
		{name: 'four', key: '0034'},
		{name: 'five', key: '0035'},
		{name: 'six', key: '0036'},
		{name: 'seven', key: '0037'},
		{name: 'eight', key: '0038'},
		{name: 'nine', key: '0039'},
		{name: 'rolling_on_the_floor_laughing', key: '1f923'},
		{name: 'smiling_face', key: '263a'},
		{name: 'lying_face', key: '1f925'},
		{name: 'drooling_face', key: '1f924'},
		{name: 'nauseated_face', key: '1f922'},
		{name: 'sneezing_face', key: '1f927'},
		{name: 'cowboy_hat_face', key: '1f920'},
		{name: 'clown_face', key: '1f921'},
		{name: 'raised_back_of_hand', key: '1f91a'},
		{name: 'crossed_fingers', key: '1f91e'},
		{name: 'call_me_hand', key: '1f919'},
		{name: 'leftfacing_fist', key: '1f91b'},
		{name: 'rightfacing_fist', key: '1f91c'},
		{name: 'handshake', key: '1f91d'},
		{name: 'selfie', key: '1f933'},
		{name: 'person_facepalming', key: '1f926'},
		{name: 'person_shrugging', key: '1f937'},
		{name: 'prince', key: '1f934'},
		{name: 'man_in_tuxedo', key: '1f935'},
		{name: 'pregnant_woman', key: '1f930'},
		{name: 'Mrs_Claus', key: '1f936'},
		{name: 'man_dancing', key: '1f57a'},
		{name: 'person_fencing', key: '1f93a'},
		{name: 'person_cartwheeling', key: '1f938'},
		{name: 'people_wrestling', key: '1f93c'},
		{name: 'person_playing_water_polo', key: '1f93d'},
		{name: 'person_playing_handball', key: '1f93e'},
		{name: 'person_juggling', key: '1f939'},
		{name: 'light_skin_tone', key: '1f3fb'},
		{name: 'mediumlight_skin_tone', key: '1f3fc'},
		{name: 'medium_skin_tone', key: '1f3fd'},
		{name: 'mediumdark_skin_tone', key: '1f3fe'},
		{name: 'dark_skin_tone', key: '1f3ff'},
		{name: 'gorilla', key: '1f98d'},
		{name: 'fox', key: '1f98a'},
		{name: 'deer', key: '1f98c'},
		{name: 'rhinoceros', key: '1f98f'},
		{name: 'bat', key: '1f987'},
		{name: 'eagle', key: '1f985'},
		{name: 'duck', key: '1f986'},
		{name: 'owl', key: '1f989'},
		{name: 'lizard', key: '1f98e'},
		{name: 'shark', key: '1f988'},
		{name: 'butterfly', key: '1f98b'},
		{name: 'wilted_flower', key: '1f940'},
		{name: 'kiwi_fruit', key: '1f95d'},
		{name: 'avocado', key: '1f951'},
		{name: 'potato', key: '1f954'},
		{name: 'carrot', key: '1f955'},
		{name: 'cucumber', key: '1f952'},
		{name: 'peanuts', key: '1f95c'},
		{name: 'croissant', key: '1f950'},
		{name: 'baguette_bread', key: '1f956'},
		{name: 'pancakes', key: '1f95e'},
		{name: 'bacon', key: '1f953'},
		{name: 'stuffed_flatbread', key: '1f959'},
		{name: 'shallow_pan_of_food', key: '1f958'},
		{name: 'green_salad', key: '1f957'},
		{name: 'shrimp', key: '1f990'},
		{name: 'squid', key: '1f991'},
		{name: 'glass_of_milk', key: '1f95b'},
		{name: 'clinking_glasses', key: '1f942'},
		{name: 'tumbler_glass', key: '1f943'},
		{name: 'spoon', key: '1f944'},
		{name: 'motor_scooter', key: '1f6f5'},
		{name: 'kick_scooter', key: '1f6f4'},
		{name: 'canoe', key: '1f6f6'},
		{name: 'umbrella2', key: '2602'},
		{name: 'snowman2', key: '2603'},
		{name: '1st_place_medal', key: '1f947'},
		{name: '2nd_place_medal', key: '1f948'},
		{name: '3rd_place_medal', key: '1f949'},
		{name: 'boxing_glove', key: '1f94a'},
		{name: 'martial_arts_uniform', key: '1f94b'},
		{name: 'goal_net', key: '1f945'},
		{name: 'envelope', key: '2709'},
		{name: 'shopping_cart', key: '1f6d2'},
		{name: 'eject_button', key: '23cf'},
		{name: 'medical_symbol', key: '2695'},
		{name: 'shinto_shrine', key: '26e9'},
		{name: 'fast_forward', key: '23e9'},
		{name: 'hash', key: '0023'},
		{name: 'asterisk', key: '002a'},
		{name: 'regional_indicator_z', key: '1f1ff'},
		{name: 'regional_indicator_y', key: '1f1fe'},
		{name: 'regional_indicator_x', key: '1f1fd'},
		{name: 'regional_indicator_w', key: '1f1fc'},
		{name: 'regional_indicator_v', key: '1f1fb'},
		{name: 'regional_indicator_t', key: '1f1f9'},
		{name: 'regional_indicator_s', key: '1f1f8'},
		{name: 'regional_indicator_r', key: '1f1f7'},
		{name: 'regional_indicator_q', key: '1f1f6'},
		{name: 'regional_indicator_p', key: '1f1f5'},
		{name: 'regional_indicator_o', key: '1f1f4'},
		{name: 'regional_indicator_n', key: '1f1f3'},
		{name: 'regional_indicator_m', key: '1f1f2'},
		{name: 'regional_indicator_l', key: '1f1f1'},
		{name: 'regional_indicator_k', key: '1f1f0'},
		{name: 'regional_indicator_j', key: '1f1ef'},
		{name: 'regional_indicator_i', key: '1f1ee'},
		{name: 'regional_indicator_h', key: '1f1ed'},
		{name: 'regional_indicator_g', key: '1f1ec'},
		{name: 'regional_indicator_e', key: '1f1ea'},
		{name: 'regional_indicator_d', key: '1f1e9'},
		{name: 'regional_indicator_c', key: '1f1e8'},
		{name: 'regional_indicator_b', key: '1f1e7'},
		{name: 'regional_indicator_a', key: '1f1e6'},

	];

	// Populated with unicode key when shortname is found in emojies array
	var emojieskey;

	/**
	 * Load in options
	 *
	 * @param {object} options
	 */
	function Elk_Emoji(options)
	{
		// All the passed options and defaults are loaded to the opts object
		this.opts = $.extend({}, this.defaults, options);
	}

	/**
	 * Helper function to see if a :tag: emoji value exists in our array
	 * if found will populate emojis key with the corresponding value
	 *
	 * @param {string} emoji
	 */
	Elk_Emoji.prototype.emojiExists = function (emoji)
	{
		return emojies.some(function (el)
		{
			if (el.name === emoji)
			{
				emojieskey = el.key;
				return true;
			}
		});
	};

	/**
	 * Attach atwho to the passed $element so we create a pull down list
	 *
	 * @param {object} oEmoji
	 * @param {object} $element
	 * @param {object} oIframeWindow
	 */
	Elk_Emoji.prototype.attachAtWho = function (oEmoji, $element, oIframeWindow)
	{
		/**
		 * Create the dropdown selection list
		 * Inserts the site image location when one is selected.
		 * Uses the CDN for the pulldown image to reduce site calls
		 */
		var tpl;

		// Use CDN calls to populate the atwho selection list
		switch(oEmoji.opts.emoji_group) {
			case 'twemoji':
				//tpl = "https://twemoji.maxcdn.com/16x16/${key}.png";
				tpl = "https://twemoji.maxcdn.com/svg/${key}.svg";
				break;
			case 'emojitwo':
				tpl = "https://rawcdn.githack.com/EmojiTwo/emojitwo/d79b4477eb8f9110fc3ce7bed2cc66030a77933e/svg/${key}.svg";
				break;
			case 'noto-emoji':
				tpl = "https://rawcdn.githack.com/googlefonts/noto-emoji/e7ac893b3315181f51710de3ba16704ec95e3f51/svg/emoji_u${key}.svg";
				break;
			default:
				tpl = "http://cdn.jsdelivr.net/emojione/assets/png/${key}.png";
		}

		// Create the emoji select list and insert choice in to the editor
		$element.atwho({
			at: ":",
			data: emojies,
			maxLen: 25,
			limit: 8,
			acceptSpaceBar: true,
			displayTpl: "<li data-value=':${name}:'><img class='emoji_tpl' src='" + tpl + "' />${name}</li>",
			insertTpl: "${name} | ${key}",
			callbacks: {
				filter: function (query, items, search_key)
				{
					// Don't show the list until they have entered at least two characters
					if (query.length < 2)
					{
						return [];
					}

					return items;
				},
				beforeInsert: function (value)
				{
					tpl = value.split(" | ");

					if (editor.inSourceMode())
					{
						return ":" + tpl[0] + ":";
					}

					return "<img class='emoji' data-sceditor-emoticon=':" + tpl[0] + ":' alt=':" + tpl[1] + ":' title='" + tpl[0] + "' src='" + oEmoji.opts.emoji_url + tpl[1] + ".svg' />";
				},
				tplEval: function (tpl, map)
				{
					try
					{
						return tpl.replace(/\$\{([^\}]*)\}/g, function(tag, key, pos)
						{
							return map[key];
						});
					}
					catch (_error)
					{
						if ('console' in window)
						{
							window.console.info(_error);
						}

						return "";
					}
				},
				beforeReposition: function (offset)
				{
					// We only need to adjust when in wysiwyg
					if (editor.inSourceMode())
					{
						return offset;
					}

					// Lets get the caret position so we can add the emoji box there
					var corrected_offset = findAtPosition();

					offset.top = corrected_offset.top;
					offset.left = corrected_offset.left;

					return offset;
				}
			}
		});

		// Don't save a draft due to a emoji window open/close
		$(oIframeWindow).on("shown.atwho", function (event, offset)
		{
			disableDrafts = true;
		});

		$(oIframeWindow).on("hidden.atwho", function (event, offset)
		{
			disableDrafts = false;
		});

		// Attach a click event to the toggle button, can't find a good plugin event to use
		// for this purpose
		if (typeof oIframeWindow !== 'undefined')
		{
			$(".sceditor-button-source").on("click", function (event, offset)
			{
				// If the button has the active class, we clicked and entered wizzy mode
				if (!$(this).hasClass("active"))
				{
					Elk_Emoji.prototype.processEmoji(oEmoji.opts.emoji_group);
				}
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
		function findAtPosition()
		{
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
			while (prev)
			{
				atPos = (prev.nodeValue || '').lastIndexOf(':');

				// Found the start of Emoji
				if (atPos > -1)
				{
					parent.insertBefore(placefinder, prev.splitText(atPos + 1));
					break;
				}

				prev = prev.previousSibling;
			}

			// If we were successful in adding the placefinder
			if (placefinder.parentNode)
			{
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
			if (offset)
			{
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
	Elk_Emoji.prototype.processEmoji = function (emoji_group)
	{
		var instance, // sceditor instance
			str, // current html in the editor
			emoji_url = elk_smileys_url.replace("default", emoji_group), // where the emoji images are
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
		for (i = 0; i < n; i++)
		{
			// Only look for emoji outside the code tags
			if (i % 4 === 0)
			{
				// Search for emoji :tags: and replace known ones with the right image
				str_split[i] = str_split[i].replace(emoji_regex, Elk_Emoji.prototype.process);
			}
		}

		// Put it all back together
		str = str_split.join('');

		// Replace the editors html with the update html
		instance.val(str, false);
	};

	Elk_Emoji.prototype.process = function(match, tag, shortname)
	{
		// Replace all valid emoji tags with the image tag
		if (typeof shortname === 'undefined' || shortname === '' || !(Elk_Emoji.prototype.emojiExists(shortname)))
		{
			return match;
		}

		return '<img data-sceditor-emoticon="' + tag + '" class="emoji" alt="' + tag + '" title="' + shortname + '" src="' + emoji_url + emojieskey + '.svg" />';
	};

	/**
	 * Private emoji vars
	 */
	Elk_Emoji.prototype.defaults = {_names: []};

	/**
	 * Holds all current emoji (defaults + passed options)
	 */
	Elk_Emoji.prototype.opts = {};

	/**
	 * Emoji plugin interface to SCEditor
	 *  - Called from the editor as a plugin
	 *  - Monitors events so we control the emoji's
	 */
	$.sceditor.plugins.emoji = function ()
	{
		var base = this,
			oEmoji;

		base.init = function ()
		{
			// Grab this instance for use use in oEmoji
			editor = this;
		};

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function ()
		{
			// Init the emoji instance, load in the options
			oEmoji = new Elk_Emoji(this.opts.emojiOptions);

			if (typeof oEmoji.opts.editor_id === 'undefined')
			{
				oEmoji.opts.editor_id = post_box_name;
			}

			emoji_url = elk_smileys_url.replace("default", oEmoji.opts.emoji_group);

			var $option_eid = $('#' + oEmoji.opts.editor_id);

			// Attach atwho to the textarea
			oEmoji.attachAtWho(oEmoji, $option_eid.parent().find('textarea'));

			// Using wysiwyg, then lets attach atwho to it as well
			var instance = $option_eid.sceditor('instance');

			if (!instance.opts.runWithoutWysiwygSupport)
			{
				// We need to monitor the iframe window and body to text input
				var oIframe = $option_eid.parent().find('iframe')[0],
					oIframeWindow = oIframe.contentWindow,
					oIframeBody = $option_eid.parent().find('iframe').contents().find('body')[0];

				oEmoji.attachAtWho(oEmoji, $(oIframeBody), oIframeWindow);
			}
		};
	};
})(jQuery, window, document);
