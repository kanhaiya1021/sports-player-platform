<?php
/**
 * Script to create 8-10 dummy player profiles
 *
 * Usage: Run this via WP-CLI or access via browser when logged in as admin
 * wp eval-file create-dummy-players.php
 *
 * Or add to a page template and visit the page once
 */

if (!defined('ABSPATH')) {
    // If running via WP-CLI, load WordPress
    if (defined('WP_CLI')) {
        require_once '../../../../../../../wp-load.php';
    } else {
        exit('Direct access not allowed. Use WP-CLI: wp eval-file create-dummy-players.php');
    }
}

if (!current_user_can('manage_options')) {
    wp_die('Unauthorized: Requires manage_options capability');
}

global $wpdb;

// Dummy player data - 10 players with varied sports, positions, and details
$dummy_players = [
    [
        'name' => 'Arjun Patel',
        'sport' => 'cricket',
        'playing_position' => 'All-rounder',
        'secondary_position' => 'Right-arm Medium Bowler',
        'dominant_side' => 'right',
        'club' => 'Mumbai Cricket Academy',
        'player_jersey' => '7',
        'height' => '178',
        'weight' => '72',
        'location' => 'Mumbai, Maharashtra',
        'date_of_birth' => '2002-03-15',
        'school' => 'St. Xavier\'s College, Mumbai',
        'graduation_year' => '2024',
        'gpa' => '8.5/10.0',
        'sat_act_scores' => '',
        'awards' => 'Best All-rounder - Inter-College Tournament 2023\nMan of the Match - Mumbai U-19 vs Delhi U-19\nSelected for India U-19 Camp 2022',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/arjunpatel_cricket\nhttps://twitter.com/arjun_patel7',
        'recruiting_status' => 'open',
        'preferred_location' => 'Mumbai, Pune, Bangalore',
        'competition_level' => 'Domestic First Class, IPL',
        'recruiting_info' => 'Looking for professional contract opportunities. Strong batting technique against spin. Available for 2024-25 domestic season.',
        'athletic_stats' => '2023-24: 45 matches, 1247 runs @ 42.31, 38 wickets @ 24.15\n2022-23: 38 matches, 980 runs @ 36.29, 31 wickets @ 26.45\n2021-22: 32 matches, 756 runs @ 32.86, 24 wickets @ 28.20',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Ranji Trophy', 'metrics' => ['matches' => 8, 'runs' => 420, 'wickets' => 12]],
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Vijay Hazare Trophy', 'metrics' => ['matches' => 7, 'runs' => 285, 'wickets' => 8]],
            ['sport' => 'cricket', 'season' => '2022-23', 'competition' => 'Ranji Trophy', 'metrics' => ['matches' => 7, 'runs' => 380, 'wickets' => 10]],
        ],
    ],
    [
        'name' => 'Priya Sharma',
        'sport' => 'cricket',
        'playing_position' => 'Opening Batter',
        'secondary_position' => 'Wicketkeeper',
        'dominant_side' => 'right',
        'club' => 'Delhi Women\'s Cricket Academy',
        'player_jersey' => '18',
        'height' => '165',
        'weight' => '58',
        'location' => 'New Delhi',
        'date_of_birth' => '2003-07-22',
        'school' => 'Lady Shri Ram College, Delhi',
        'graduation_year' => '2025',
        'gpa' => '8.8/10.0',
        'sat_act_scores' => 'SAT: 1420',
        'awards' => 'Player of the Tournament - Senior Women\'s T20 Trophy 2023\nEmerging Player - Women\'s Premier League 2024\nBest Batter - Inter-Zonal U-19 2022',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/priyasharma_cricket\nhttps://linkedin.com/in/priya-sharma-cricket',
        'recruiting_status' => 'open',
        'preferred_location' => 'Delhi, Mumbai, Bangalore',
        'competition_level' => 'WPL, India A, Senior Domestic',
        'recruiting_info' => 'Aggressive opening batter with wicketkeeping skills. Strike rate 135+ in T20s. Seeking WPL franchise contract for 2025 season.',
        'athletic_stats' => '2023-24: 18 matches, 624 runs @ 36.70, SR 138.2\n2022-23: 15 matches, 542 runs @ 38.71, SR 132.4\n2021-22: 12 matches, 398 runs @ 33.16, SR 128.9',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Women\'s Premier League', 'metrics' => ['matches' => 10, 'runs' => 380, 'wickets' => 0]],
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Senior Women\'s T20 Trophy', 'metrics' => ['matches' => 8, 'runs' => 244, 'wickets' => 0]],
        ],
    ],
    [
        'name' => 'Rahul Singh',
        'sport' => 'football',
        'playing_position' => 'Striker',
        'secondary_position' => 'Left Winger',
        'dominant_side' => 'left',
        'club' => 'Bengaluru FC Reserves',
        'player_jersey' => '9',
        'height' => '182',
        'weight' => '76',
        'location' => 'Bengaluru, Karnataka',
        'date_of_birth' => '2001-11-08',
        'school' => 'Christ University, Bengaluru',
        'graduation_year' => '2023',
        'gpa' => '3.7/4.0',
        'sat_act_scores' => '',
        'awards' => 'Top Scorer - I-League 2nd Division 2023-24 (14 goals)\nPlayer of the Month - November 2023\nKarnataka State Football Championship Winner 2022',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/rahulsingh_fc9\nhttps://twitter.com/rahul_singh9',
        'recruiting_status' => 'committed',
        'preferred_location' => 'Bengaluru, Chennai, Goa',
        'competition_level' => 'ISL, I-League',
        'recruiting_info' => 'Signed with Bengaluru FC for 2024-26. Clinical finisher with both feet. Strong aerial ability. 28 goals in 45 appearances for reserves.',
        'athletic_stats' => '2023-24: 22 apps, 14 goals, 5 assists\n2022-23: 18 apps, 11 goals, 3 assists\n2021-22: 15 apps, 8 goals, 4 assists',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'football', 'season' => '2023-24', 'competition' => 'I-League 2nd Division', 'metrics' => ['appearances' => 22, 'goals' => 14, 'assists' => 5]],
            ['sport' => 'football', 'season' => '2022-23', 'competition' => 'I-League 2nd Division', 'metrics' => ['appearances' => 18, 'goals' => 11, 'assists' => 3]],
            ['sport' => 'football', 'season' => '2022-23', 'competition' => 'Karnataka State Championship', 'metrics' => ['appearances' => 6, 'goals' => 4, 'assists' => 2]],
        ],
    ],
    [
        'name' => 'Ananya Reddy',
        'sport' => 'football',
        'playing_position' => 'Central Midfielder',
        'secondary_position' => 'Defensive Midfielder',
        'dominant_side' => 'right',
        'club' => 'Gokulam Kerala FC Women',
        'player_jersey' => '8',
        'height' => '170',
        'weight' => '62',
        'location' => 'Hyderabad, Telangana',
        'date_of_birth' => '2002-05-12',
        'school' => 'University of Hyderabad',
        'graduation_year' => '2024',
        'gpa' => '3.9/4.0',
        'sat_act_scores' => 'SAT: 1480',
        'awards' => 'Indian Women\'s League Winner 2023-24\nBest Midfielder - IWL 2023-24\nIndia U-20 Captain - SAFF U-20 Championship 2022',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/ananya_reddy8\nhttps://linkedin.com/in/ananya-reddy-football',
        'recruiting_status' => 'open',
        'preferred_location' => 'Kerala, Tamil Nadu, Maharashtra',
        'competition_level' => 'IWL, India Senior Team',
        'recruiting_info' => 'Box-to-box midfielder with excellent passing range. 85% pass accuracy. Leadership experience as U-20 captain. Seeking professional contract for 2024-25 season.',
        'athletic_stats' => '2023-24: 16 apps, 4 goals, 7 assists\n2022-23: 14 apps, 3 goals, 5 assists\n2021-22: 12 apps, 2 goals, 4 assists',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'football', 'season' => '2023-24', 'competition' => 'Indian Women\'s League', 'metrics' => ['appearances' => 16, 'goals' => 4, 'assists' => 7]],
            ['sport' => 'football', 'season' => '2022-23', 'competition' => 'Indian Women\'s League', 'metrics' => ['appearances' => 14, 'goals' => 3, 'assists' => 5]],
        ],
    ],
    [
        'name' => 'Vikram Choudhary',
        'sport' => 'cricket',
        'playing_position' => 'Fast Bowler',
        'secondary_position' => 'Lower Order Batter',
        'dominant_side' => 'right',
        'club' => 'Rajasthan Royals Academy',
        'player_jersey' => '99',
        'height' => '190',
        'weight' => '85',
        'location' => 'Jaipur, Rajasthan',
        'date_of_birth' => '2000-09-30',
        'school' => 'Manipal University, Jaipur',
        'graduation_year' => '2022',
        'gpa' => '7.2/10.0',
        'sat_act_scores' => '',
        'awards' => 'Purple Cap - Syed Mushtaq Ali Trophy 2023 (18 wickets)\nFastest Ball - IPL 2024 (152.3 km/h)\nIndia A Selection - Tour of South Africa 2023',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/vikramchoudhary99\nhttps://twitter.com/vikram_c99',
        'recruiting_status' => 'open',
        'preferred_location' => 'Rajasthan, Gujarat, Mumbai',
        'competition_level' => 'IPL, Ranji Trophy, India A',
        'recruiting_info' => 'Express pace bowler consistently hitting 145+. Excellent yorker at death. 45 wickets in 28 T20s at economy 7.2. Available for IPL 2025 auction.',
        'athletic_stats' => '2023-24: 28 matches, 45 wickets @ 18.40, Econ 7.2\n2022-23: 22 matches, 34 wickets @ 21.15, Econ 7.8\n2021-22: 18 matches, 26 wickets @ 23.40, Econ 8.1',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Syed Mushtaq Ali Trophy', 'metrics' => ['matches' => 10, 'runs' => 45, 'wickets' => 18]],
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'IPL', 'metrics' => ['matches' => 12, 'runs' => 18, 'wickets' => 14]],
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Ranji Trophy', 'metrics' => ['matches' => 6, 'runs' => 32, 'wickets' => 13]],
        ],
    ],
    [
        'name' => 'Sneha Gupta',
        'sport' => 'cricket',
        'playing_position' => 'Leg Spinner',
        'secondary_position' => 'Middle Order Batter',
        'dominant_side' => 'right',
        'club' => 'Punjab Cricket Association',
        'player_jersey' => '23',
        'height' => '162',
        'weight' => '55',
        'location' => 'Chandigarh, Punjab',
        'date_of_birth' => '2004-01-18',
        'school' => 'Panjab University, Chandigarh',
        'graduation_year' => '2026',
        'gpa' => '8.2/10.0',
        'sat_act_scores' => 'SAT: 1350',
        'awards' => 'Best Bowler - U-19 Women\'s T20 World Cup 2023 (12 wickets)\nEmerging Player - Senior Women\'s T20 Trophy 2023-24\nPunjab State Cricketer of the Year 2023',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/sneha_gupta23\nhttps://twitter.com/sneha_g_cricket',
        'recruiting_status' => 'open',
        'preferred_location' => 'Chandigarh, Mohali, Delhi',
        'competition_level' => 'WPL, India U-19, Senior Domestic',
        'recruiting_info' => 'Young leg-spinner with excellent control and variations. Googly and flipper are key weapons. 4/12 best figures in T20. U-19 World Cup winner 2023.',
        'athletic_stats' => '2023-24: 15 matches, 22 wickets @ 14.50, Econ 5.8\n2022-23: 12 matches, 18 wickets @ 16.20, Econ 6.1\n2021-22: 10 matches, 14 wickets @ 18.30, Econ 6.4',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Senior Women\'s T20 Trophy', 'metrics' => ['matches' => 8, 'runs' => 85, 'wickets' => 14]],
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'U-19 Women\'s T20 World Cup', 'metrics' => ['matches' => 7, 'runs' => 42, 'wickets' => 12]],
        ],
    ],
    [
        'name' => 'Karan Malhotra',
        'sport' => 'football',
        'playing_position' => 'Centre Back',
        'secondary_position' => 'Right Back',
        'dominant_side' => 'right',
        'club' => 'Mohun Bagan SG Reserves',
        'player_jersey' => '4',
        'height' => '188',
        'weight' => '82',
        'location' => 'Kolkata, West Bengal',
        'date_of_birth' => '2001-02-28',
        'school' => 'Jadavpur University, Kolkata',
        'graduation_year' => '2023',
        'gpa' => '3.5/4.0',
        'sat_act_scores' => '',
        'awards' => 'Best Defender - Calcutta Football League 2023\nISL Cup Winner 2023-24 (squad member)\nIndia U-23 Selection - Asian Games 2023',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com karan_malhotra4\nhttps://linkedin.com/in/karan-malhotra-fc',
        'recruiting_status' => 'committed',
        'preferred_location' => 'Kolkata, Mumbai, Goa',
        'competition_level' => 'ISL, I-League',
        'recruiting_info' => 'Contracted with Mohun Bagan SG until 2026. Strong aerial presence, 78% duel win rate. Comfortable playing out from back. Leadership qualities - captained U-23 side.',
        'athletic_stats' => '2023-24: 20 apps, 2 goals, 1 assist, 8 clean sheets\n2022-23: 18 apps, 1 goal, 0 assists, 7 clean sheets\n2021-22: 16 apps, 0 goals, 2 assists, 6 clean sheets',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'football', 'season' => '2023-24', 'competition' => 'Calcutta Football League', 'metrics' => ['appearances' => 12, 'goals' => 2, 'assists' => 1]],
            ['sport' => 'football', 'season' => '2023-24', 'competition' => 'ISL (Reserves)', 'metrics' => ['appearances' => 8, 'goals' => 0, 'assists' => 0]],
        ],
    ],
    [
        'name' => 'Meera Joshi',
        'sport' => 'football',
        'playing_position' => 'Goalkeeper',
        'secondary_position' => '',
        'dominant_side' => 'right',
        'club' => 'Sethu FC',
        'player_jersey' => '1',
        'height' => '175',
        'weight' => '68',
        'location' => 'Chennai, Tamil Nadu',
        'date_of_birth' => '2002-08-14',
        'school' => 'Anna University, Chennai',
        'graduation_year' => '2024',
        'gpa' => '3.8/4.0',
        'sat_act_scores' => 'SAT: 1400',
        'awards' => 'Golden Glove - Indian Women\'s League 2023-24 (9 clean sheets)\nBest GK - SAFF Women\'s Championship 2022\nTamil Nadu State Best Goalkeeper 2021, 2022, 2023',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/meera_joshi1\nhttps://twitter.com/meera_joshi_gk',
        'recruiting_status' => 'open',
        'preferred_location' => 'Chennai, Bengaluru, Kerala',
        'competition_level' => 'IWL, India Senior Team',
        'recruiting_info' => 'Shot-stopper with excellent command of area. 78% save percentage. Strong distribution with both hands and feet. 35 clean sheets in 60 senior appearances. Seeking IWL 2024-25 contract.',
        'athletic_stats' => '2023-24: 14 apps, 9 clean sheets, 78% save %\n2022-23: 12 apps, 7 clean sheets, 75% save %\n2021-22: 10 apps, 5 clean sheets, 72% save %',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'football', 'season' => '2023-24', 'competition' => 'Indian Women\'s League', 'metrics' => ['appearances' => 14, 'goals' => 0, 'assists' => 0]],
            ['sport' => 'football', 'season' => '2022-23', 'competition' => 'Indian Women\'s League', 'metrics' => ['appearances' => 12, 'goals' => 0, 'assists' => 0]],
        ],
    ],
    [
        'name' => 'Aditya Nair',
        'sport' => 'cricket',
        'playing_position' => 'Wicketkeeper-Batter',
        'secondary_position' => 'Opening Batter',
        'dominant_side' => 'right',
        'club' => 'Kerala Cricket Association',
        'player_jersey' => '12',
        'height' => '175',
        'weight' => '70',
        'location' => 'Kochi, Kerala',
        'date_of_birth' => '2003-04-25',
        'school' => 'IIT Palakkad',
        'graduation_year' => '2025',
        'gpa' => '9.1/10.0',
        'sat_act_scores' => 'JEE Advanced: AIR 2,847',
        'awards' => 'Best Wicketkeeper - Ranji Trophy 2023-24 (28 dismissals)\nPlayer of the Match - Kerala vs Tamil Nadu (145* & 5 catches)\nIndia U-19 Selection - Asia Cup 2022',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/aditya_nair12\nhttps://linkedin.com/in/aditya-nair-cricket',
        'recruiting_status' => 'open',
        'preferred_location' => 'Kerala, Bangalore, Chennai',
        'competition_level' => 'Ranji Trophy, IPL, India A',
        'recruiting_info' => 'Smart wicketkeeper-batter with solid technique. 28 dismissals in 8 Ranji matches. Average 45+ in first-class. Academic excellence (IIT). Looking for IPL 2025 opportunity.',
        'athletic_stats' => '2023-24: 8 matches, 480 runs @ 48.00, 28 dismissals\n2022-23: 7 matches, 385 runs @ 38.50, 22 dismissals\n2021-22: 6 matches, 290 runs @ 32.22, 18 dismissals',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 1],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Ranji Trophy', 'metrics' => ['matches' => 8, 'runs' => 480, 'wickets' => 0]],
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Vijay Hazare Trophy', 'metrics' => ['matches' => 6, 'runs' => 245, 'wickets' => 0]],
        ],
    ],
    [
        'name' => 'Kavya Iyer',
        'sport' => 'cricket',
        'playing_position' => 'Left-arm Spinner',
        'secondary_position' => 'Lower Order Batter',
        'dominant_side' => 'left',
        'club' => 'Tamil Nadu Cricket Association',
        'player_jersey' => '33',
        'height' => '168',
        'weight' => '60',
        'location' => 'Chennai, Tamil Nadu',
        'date_of_birth' => '2001-12-03',
        'school' => 'Stella Maris College, Chennai',
        'graduation_year' => '2023',
        'gpa' => '3.6/4.0',
        'sat_act_scores' => '',
        'awards' => 'Player of the Tournament - Senior Women\'s One Day Trophy 2023\nMost Wickets - Senior Women\'s T20 Trophy 2023-24 (20 wickets)\nIndia A Selection - Tour of Australia 2023',
        'avatar_url' => '',
        'image_gallery' => '',
        'videos_highlights' => '',
        'social_media' => 'https://instagram.com/kavya_iyer33\nhttps://twitter.com/kavya_iyer_cricket',
        'recruiting_status' => 'unavailable',
        'preferred_location' => 'Chennai, Bangalore, Mumbai',
        'competition_level' => 'WPL, India A, Senior Domestic',
        'recruiting_info' => 'Currently on medical leave (shoulder rehab). Expected return: Oct 2024. Left-arm orthodox with excellent control. 45 wickets in 2023-24 domestic season at avg 16.2. Not available for immediate contracts.',
        'athletic_stats' => '2023-24: 12 matches, 20 wickets @ 14.80, Econ 4.9\n2022-23: 14 matches, 25 wickets @ 15.40, Econ 5.1\n2021-22: 10 matches, 16 wickets @ 18.50, Econ 5.6',
        'team_seasons' => [
            ['team_id' => 0, 'season' => '2023-24', 'current' => 0],
            ['team_id' => 0, 'season' => '2022-23', 'current' => 0],
            ['team_id' => 0, 'season' => '2021-22', 'current' => 0],
        ],
        'season_stats' => [
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Senior Women\'s T20 Trophy', 'metrics' => ['matches' => 8, 'runs' => 35, 'wickets' => 14]],
            ['sport' => 'cricket', 'season' => '2023-24', 'competition' => 'Senior Women\'s One Day Trophy', 'metrics' => ['matches' => 6, 'runs' => 28, 'wickets' => 10]],
            ['sport' => 'cricket', 'season' => '2022-23', 'competition' => 'Senior Women\'s T20 Trophy', 'metrics' => ['matches' => 8, 'runs' => 42, 'wickets' => 16]],
        ],
    ],
];

echo "Creating " . count($dummy_players) . " dummy player profiles...\n\n";

$created = 0;
$errors = [];

foreach ($dummy_players as $index => $player_data) {
    try {
        // Create the player post
        $post_id = wp_insert_post([
            'post_title'   => $player_data['name'],
            'post_type'    => 'player',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_content' => '<p>' . sprintf(__('Professional %s player from %s.', 'sports-player-platform'), $player_data['sport'], $player_data['location']) . '</p>',
        ], true);

        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        // Prepare meta data for SPP_Player_Profile::save()
        $meta = [];
        foreach ($player_data as $key => $value) {
            if (!in_array($key, ['team_seasons', 'season_stats', 'athletic_stats'], true)) {
                $meta[$key] = $value;
            }
        }

        // Save player profile meta
        require_once SPP_PLUGIN_DIR . 'includes/player/class-spp-player-profile.php';
        $invalid = SPP_Player_Profile::save($post_id, $meta);

        if ($invalid) {
            throw new Exception('Invalid fields: ' . implode(', ', $invalid));
        }

        // Save team_seasons and season_stats separately (complex group types)
        if (!empty($player_data['team_seasons'])) {
            $teams_meta = ['rows' => $player_data['team_seasons'], 'present' => '1'];
            update_post_meta($post_id, 'team_seasons', wp_slash($teams_meta));
        }

        if (!empty($player_data['season_stats'])) {
            $stats_meta = ['rows' => $player_data['season_stats'], 'present' => '1'];
            update_post_meta($post_id, 'season_stats', wp_slash($stats_meta));
        }

        // Save athletic_stats as textarea
        if (!empty($player_data['athletic_stats'])) {
            update_post_meta($post_id, 'athletic_stats', sanitize_textarea_field($player_data['athletic_stats']));
        }

        echo "✓ Created: {$player_data['name']} (ID: $post_id) - {$player_data['sport']} / {$player_data['playing_position']}\n";
        $created++;

    } catch (Exception $e) {
        $errors[] = "Failed to create {$player_data['name']}: " . $e->getMessage();
        echo "✗ Error: {$player_data['name']} - " . $e->getMessage() . "\n";
    }
}

echo "\n--- Summary ---\n";
echo "Created: $created / " . count($dummy_players) . " players\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\nDone! Visit wp-admin/admin.php?page=spp-players to view the players.\n";