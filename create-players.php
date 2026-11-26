<?php
/**
 * Create 10 dummy player profiles with comprehensive metadata
 *
 * Run via: php create-players.php or wp eval-file create-players.php
 */

// Load WordPress
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once __DIR__ . '/../../../wp-load.php';
} elseif (defined('ABSPATH')) {
    // Already loaded
} else {
    die("Cannot find wp-load.php\n");
}

// Player data - 10 players with realistic sports data
$players = [
    [
        'id' => null,
        'data' => [
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
            'school' => "St. Xavier's College, Mumbai",
            'graduation_year' => '2024',
            'gpa' => '8.5/10.0',
            'awards' => "Best All-rounder - Inter-College Tournament 2023\nMan of the Match - Mumbai U-19 vs Delhi U-19\nSelected for India U-19 Camp 2022",
            'recruiting_status' => 'open',
            'sat_act_scores' => 'SAT: 1350',
            'preferred_location' => 'Mumbai, Pune, Bangalore',
            'competition_level' => 'Domestic First Class, IPL',
            'recruiting_info' => 'Looking for professional contract opportunities. Strong batting technique against spin. Available for 2024-25 domestic season.',
            'athletic_stats' => "2023-24: 45 matches, 1247 runs @ 42.31, 38 wickets @ 24.15\n2022-23: 38 matches, 980 runs @ 36.29, 31 wickets @ 26.45\n2021-22: 32 matches, 756 runs @ 32.86, 24 wickets @ 28.20",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'cricket',
            'playing_position' => 'Opening Batter',
            'secondary_position' => 'Wicketkeeper',
            'dominant_side' => 'right',
            'club' => "Delhi Women's Cricket Academy",
            'player_jersey' => '18',
            'height' => '165',
            'weight' => '58',
            'location' => 'New Delhi',
            'date_of_birth' => '2003-07-22',
            'school' => 'Lady Shri Ram College, Delhi',
            'graduation_year' => '2025',
            'gpa' => '8.8/10.0',
            'sat_act_scores' => 'SAT: 1420',
            'awards' => "Player of the Tournament - Senior Women's T20 Trophy 2023\nEmerging Player - Women's Premier League 2024\nBest Batter - Inter-Zonal U-19 2022",
            'recruiting_status' => 'open',
            'preferred_location' => 'Delhi, Mumbai, Bangalore',
            'competition_level' => 'WPL, India A, Senior Domestic',
            'recruiting_info' => 'Aggressive opening batter with wicketkeeping skills. Strike rate 135+ in T20s. Seeking WPL franchise contract for 2025 season.',
            'athletic_stats' => "2023-24: 18 matches, 624 runs @ 36.70, SR 138.2\n2022-23: 15 matches, 542 runs @ 38.71, SR 132.4\n2021-22: 12 matches, 398 runs @ 33.16, SR 128.9",
        ]
    ],
    [
        'id' => null,
        'data' => [
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
            'awards' => "Top Scorer - I-League 2nd Division 2023-24 (14 goals)\nPlayer of the Month - November 2023\nIndia U-20 Selection - SAFF U-20 Championship 2022",
            'recruiting_status' => 'committed',
            'preferred_location' => 'Bengaluru, Chennai, Goa',
            'competition_level' => 'ISL, I-League',
            'recruiting_info' => 'Signed with Bengaluru FC for 2024-26. Clinical finisher with both feet. Strong aerial ability. 28 goals in 45 appearances for reserves.',
            'athletic_stats' => "2023-24: 22 apps, 14 goals, 5 assists\n2022-23: 18 apps, 11 goals, 3 assists\n2021-22: 15 apps, 8 goals, 4 assists",
        ]
    ],
    [
        'id' => null,
        'data' => [
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
            'awards' => "Indian Women's League Winner 2023-24\nBest Midfielder - IWL 2023-24\nIndia U-20 Captain - SAFF U-20 Championship 2022",
            'recruiting_status' => 'open',
            'preferred_location' => 'Kerala, Tamil Nadu, Maharashtra',
            'competition_level' => 'IWL, India Senior Team',
            'recruiting_info' => 'Box-to-box midfielder with excellent passing range. 85% pass accuracy. Leadership experience as U-20 captain.',
            'athletic_stats' => "2023-24: 16 apps, 4 goals, 7 assists\n2022-23: 14 apps, 3 goals, 5 assists\n2021-22: 12 apps, 2 goals, 4 assists",
        ]
    ],
    [
        'id' => null,
        'data' => [
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
            'awards' => "Purple Cap - Syed Mushtaq Ali Trophy 2023 (18 wickets)\nFastest Ball - IPL 2024 (152.3 km/h)\nIndia A Selection - Tour of South Africa 2023",
            'recruiting_status' => 'open',
            'preferred_location' => 'Rajasthan, Gujarat, Mumbai',
            'competition_level' => 'IPL, Ranji Trophy, India A',
            'recruiting_info' => 'Express pace bowler consistently hitting 145+. Excellent yorker at death. 45 wickets in 28 T20s at economy 7.2. Available for IPL 2025 auction.',
            'athletic_stats' => "2023-24: 28 matches, 45 wickets @ 18.40, Econ 7.2\n2022-23: 22 matches, 34 wickets @ 21.15, Econ 7.8\n2021-22: 18 matches, 26 wickets @ 23.40, Econ 8.1",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'cricket',
            'playing_position' => 'Leg Spinner',
            'secondary_position' => 'Middle Order Batter',
            'dominant_side' => 'right',
            'club' => "Punjab Women's Cricket Association",
            'player_jersey' => '23',
            'height' => '162',
            'weight' => '55',
            'location' => 'Chandigarh, Punjab',
            'date_of_birth' => '2004-01-18',
            'school' => 'Panjab University, Chandigarh',
            'graduation_year' => '2026',
            'gpa' => '8.2/10.0',
            'awards' => "Best Bowler - U-19 Women's T20 World Cup 2023 (12 wickets)\nEmerging Player - Senior Women's T20 Trophy 2023-24\nPunjab State Cricketer of the Year 2023",
            'recruiting_status' => 'open',
            'preferred_location' => 'Chandigarh, Mohali, Delhi',
            'competition_level' => 'WPL, India U-19, Senior Domestic',
            'recruiting_info' => 'Young leg-spinner with excellent control and variations. Googly and flipper are key weapons. 45 wickets in 2023-24 domestic season at avg 16.2.',
            'athletic_stats' => "2023-24: 15 matches, 22 wickets @ 14.50, Econ 5.8\n2022-23: 12 matches, 18 wickets @ 16.20, Econ 6.1\n2021-22: 10 matches, 14 wickets @ 18.30, Econ 6.4",
        ]
    ],
    [
        'id' => null,
        'data' => [
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
            'awards' => "Best Defender - Calcutta Football League 2023\nISL Cup Winner 2023-24 (squad member)\nIndia U-23 Selection - Asian Games 2023",
            'recruiting_status' => 'committed',
            'preferred_location' => 'Kolkata, Mumbai, Goa',
            'competition_level' => 'ISL, I-League',
            'recruiting_info' => 'Contracted with Mohun Bagan SG until 2026. Strong aerial presence, 78% duel win rate. Leadership qualities - captained U-23 side.',
            'athletic_stats' => "2023-24: 20 apps, 2 goals, 1 assist, 8 clean sheets\n2022-23: 18 apps, 1 goal, 0 assists, 7 clean sheets\n2021-22: 16 apps, 0 goals, 2 assists, 6 clean sheets",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'football',
            'playing_position' => 'Goalkeeper',
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
            'awards' => "Golden Glove - Indian Women's League 2023-24 (9 clean sheets)\nBest GK - SAFF Women's Championship 2022\nTamil Nadu State Best Goalkeeper 2021, 2022, 2023",
            'recruiting_status' => 'open',
            'preferred_location' => 'Chennai, Bengaluru, Kerala',
            'competition_level' => 'IWL, India Senior Team',
            'recruiting_info' => 'Shot-stopper with excellent command of area. 78% save percentage. Strong distribution with both hands and feet.',
            'athletic_stats' => "2023-24: 14 apps, 9 clean sheets, 78% save %\n2022-23: 12 apps, 7 clean sheets, 75% save %\n2021-22: 10 apps, 5 clean sheets, 72% save %",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'football',
            'playing_position' => 'Left Back',
            'secondary_position' => '',
            'dominant_side' => 'left',
            'club' => 'Chennai City FC',
            'player_jersey' => '3',
            'height' => '172',
            'weight' => '68',
            'location' => 'Chennai, Tamil Nadu',
            'date_of_birth' => '2000-06-21',
            'school' => 'Saveetha Institute of Medical and Technology',
            'graduation_year' => '2022',
            'gpa' => '8.1/10.0',
            'awards' => "Player of the Year - Tamil Nadu State League 2022-23\nIndia U-23 Call-up - SAFF Championship 2023\nBest Defensive Midfielder - School Tournaments 2018-2020",
            'recruiting_status' => 'closed',
            'preferred_location' => 'Tamil Nadu, Kerala, Karnataka',
            'competition_level' => 'ISL, I-League',
            'recruiting_info' => 'Currently contracted to Chennai City FC until 2025. Primarily a left-back with strong overlapping runs. 3 assists in 2023-24 season.',
            'athletic_stats' => "2023-24: 18 apps, 0 goals, 3 assists\n2022-23: 20 apps, 1 goal, 2 assists\n2021-22: 15 apps, 0 goals, 1 assists",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'football',
            'playing_position' => 'Forward',
            'secondary_position' => 'Right Wing',
            'dominant_side' => 'right',
            'club' => 'Odisha FC',
            'player_jersey' => '11',
            'height' => '180',
            'weight' => '74',
            'location' => 'Bhubaneswar, Odisha',
            'date_of_birth' => '2002-04-12',
            'school' => 'Utkal University, Bhubaneswar',
            'graduation_year' => '2024',
            'gpa' => '3.6/4.0',
            'awards' => "ISL Top Scorer - 2023-24 (12 goals)\nPlayer of the Match - Odisha Derby 2023\nO League Golden Boot 2022 (8 goals)",
            'recruiting_status' => 'open',
            'preferred_location' => 'Odisha, West Bengal, Assam',
            'competition_level' => 'ISL, I-League',
            'recruiting_info' => 'Clinical finisher with 12 goals in ISL 2023-24. Excellent positioning and composure in front of goal. Looking for continued development with Odisha FC.',
            'athletic_stats' => "2023-24: 22 apps, 12 goals, 4 assists\n2022-23: 18 apps, 8 goals, 3 assists\n2021-22: 16 apps, 5 goals, 2 assists",
        ]
    ],
];

echo "Creating " . count($players) . " dummy player profiles...\n\n";

$created = 0;
$errors = [];

foreach ($players as &$player) {
    $name = $player['data']['club'] ? $player['data']['playing_position'] : '';

    try {
        // Create the player post
        $post_id = wp_insert_post([
            'post_title'   => $player['data']['school'] ? substr($player['data']['school'], 0, 30) : 'Player',
            'post_type'    => 'player',
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_content' => '<p>Professional sports player from ' . esc_html($player['data']['location']) . '.</p>',
        ], true);

        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        // Generate name from club/sport for display
        $names = ['Arjun Patel', 'Priya Sharma', 'Rahul Singh', 'Ananya Reddy', 'Vikram Choudhary',
                  'Meera Joshi', 'Karan Malhotra', 'Aditya Nair', 'Kavya Iyer', 'Sneha Verma'];
        $name = $names[$created] ?? 'Player ' . ($created + 1);

        // Update post title with name
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $name,
        ]);

        // Save all meta fields
        foreach ($player['data'] as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, $value);
            }
        }

        echo "Created: $name (ID: $post_id)\n";
        $created++;

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Summary ---\n";
echo "Created: $created / " . count($players) . " players\n";

if (!empty($errors)) {
    echo "Errors:\n" . implode("\n", $errors) . "\n";
}

echo "\nDone! You can now view players at: http://faast.local/wp-admin/admin.php?page=spp-players\n";