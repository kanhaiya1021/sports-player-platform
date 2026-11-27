<?php
/**
 * Create 10 basketball dummy player profiles with comprehensive metadata
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

// Player data - 10 basketball players with English names
$players = [
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Point Guard',
            'secondary_position' => 'Shooting Guard',
            'dominant_side' => 'right',
            'club' => 'New York Knicks Academy',
            'player_jersey' => '3',
            'height' => '185',
            'weight' => '78',
            'location' => 'Brooklyn, New York',
            'date_of_birth' => '2003-04-12',
            'school' => "St. John's University, New York",
            'graduation_year' => '2025',
            'gpa' => '3.8/4.0',
            'awards' => "Big East All-Freshman Team 2023\nConference Player of the Week (3x) 2023-24\nNCAA Tournament MVP - Regional Finals",
            'recruiting_status' => 'open',
            'sat_act_scores' => 'SAT: 1450',
            'preferred_location' => 'New York, Boston, Chicago, Los Angeles',
            'competition_level' => 'NBA G League, NCAA Division I',
            'recruiting_info' => 'Explosive playmaker with 7.2 assists per game. Elite ball-handling and court vision. NBA Draft combine invitation. Projected lottery pick.',
            'athletic_stats' => "2023-24: 32 games, 15.8 PPG, 7.2 APG, 2.1 RPG\n2022-23: 28 games, 12.4 PPG, 5.8 APG, 1.8 RPG\n2021-22: 18 games, 8.2 PPG, 3.5 APG, 1.2 RPG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Shooting Guard',
            'secondary_position' => 'Small Forward',
            'dominant_side' => 'right',
            'club' => 'Los Angeles Lakers Development Team',
            'player_jersey' => '22',
            'height' => '191',
            'weight' => '85',
            'location' => 'Los Angeles, California',
            'date_of_birth' => '2002-11-05',
            'school' => 'University of California, Los Angeles',
            'graduation_year' => '2024',
            'gpa' => '3.9/4.0',
            'awards' => "All-Pac-12 First Team 2023-24\nKareem Abdul-Jabbar Award Winner 2024\nOlympic U-23 Team Selection 2023",
            'recruiting_status' => 'committed',
            'preferred_location' => 'Los Angeles, San Diego, Seattle, Denver',
            'competition_level' => 'NBA G League, Summer League, USA Basketball',
            'recruiting_info' => '3-point specialist with 42% shooting from deep. Contracted with Lakers for 2 years. Aiming for NBA rotation spot in 2024-25 season.',
            'athletic_stats' => "2023-24: 35 games, 18.5 PPG, 3.8 RPG, 42% 3PT\n2022-23: 32 games, 15.2 PPG, 3.5 RPG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Small Forward',
            'secondary_position' => 'Power Forward',
            'dominant_side' => 'left',
            'club' => 'Chicago Bulls Elite',
            'player_jersey' => '12',
            'height' => '203',
            'weight' => '102',
            'location' => 'Chicago, Illinois',
            'date_of_birth' => '2001-06-23',
            'school' => 'DePaul University, Chicago',
            'graduation_year' => '2023',
            'gpa' => '3.6/4.0',
            'awards' => "Big East Player of the Year 2022-23\nNABC All-America Third Team 2023\nNike Coach of the Year Award 2023",
            'recruiting_status' => 'open',
            'sat_act_scores' => 'SAT: 1380',
            'preferred_location' => 'Chicago, Detroit, Toronto, Atlanta',
            'competition_level' => 'NBA G League, Summer League',
            'recruiting_info' => 'Versatile wing with 4-year college career. Strong rebounding (8.5 RPG) and perimeter shooting. Ready for NBA call-up this season.',
            'athletic_stats' => "2023-24: 28 games, 14.2 PPG, 8.5 RPG, 2.3 APG\n2022-23: 34 games, 16.1 PPG, 9.1 RPG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Power Forward',
            'secondary_position' => 'Center',
            'dominant_side' => 'right',
            'club' => 'Boston Celtics II',
            'player_jersey' => '55',
            'height' => '208',
            'weight' => '112',
            'location' => 'Boston, Massachusetts',
            'date_of_birth' => '2000-02-14',
            'school' => 'University of Massachusetts Amherst',
            'graduation_year' => '2022',
            'gpa' => '3.4/4.0',
            'awards' => "America East Player of the Year 2021-22\nACC Tournament MVP 2022\nNBA Summer League MVP 2022",
            'recruiting_status' => 'closed',
            'preferred_location' => 'Boston, New York, Miami, Philadelphia',
            'competition_level' => 'NBA G League',
            'recruiting_info' => 'Drafted in 2nd round (45th overall) by Celtics. High-energy player with excellent post moves. Averaging 7.2 PPG with Celtics II.',
            'athletic_stats' => "2023-24: 25 games, 7.2 PPG, 5.8 RPG, 60% FT\n2022-23: 30 games, 8.5 PPG, 6.4 RPG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Center',
            'secondary_position' => 'Power Forward',
            'dominant_side' => 'right',
            'club' => 'Golden State Warriors Development',
            'player_jersey' => '42',
            'height' => '211',
            'weight' => '118',
            'location' => 'San Francisco, California',
            'date_of_birth' => '2003-09-18',
            'school' => 'University of San Francisco',
            'graduation_year' => '2025',
            'gpa' => '3.7/4.0',
            'awards' => "WCC Freshman of the Year 2022-23\nAll-America Third Team (AP) 2024\nNBA Draft Combine Invitee 2025",
            'recruiting_status' => 'open',
            'sat_act_scores' => 'SAT: 1420',
            'preferred_location' => 'San Francisco, Los Angeles, Denver, Phoenix',
            'competition_level' => 'NCAA Division I, NBA G League',
            'recruiting_info' => '7-foot-1 center with range out to 3-point line. NBA Draft combine invitation. Projected lottery pick. Excellent defensive presence.',
            'athletic_stats' => "2023-24: 34 games, 15.8 PPG, 10.2 RPG, 2.8 BPG\n2022-23: 28 games, 12.4 PPG, 8.7 RPG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Point Guard',
            'secondary_position' => 'Shooting Guard',
            'dominant_side' => 'right',
            'club' => 'Miami Heat Select',
            'player_jersey' => '1',
            'height' => '188',
            'weight' => '83',
            'location' => 'Miami, Florida',
            'date_of_birth' => '1999-12-30',
            'school' => 'University of Miami',
            'graduation_year' => '2021',
            'gpa' => '3.5/4.0',
            'awards' => "ACC All-Tournament Team 2021\nNBA G League Champion 2022\nMiami Heat Training Camp Invitee 2023",
            'recruiting_status' => 'closed',
            'preferred_location' => 'Miami, Orlando, Atlanta, Dallas',
            'competition_level' => 'NBA, NBA G League',
            'recruiting_info' => 'Undrafted free agent. Signed two-way contract with Heat. Averaging 6.1 PPG and 4.3 APG in G League assignments. Strong perimeter defender.',
            'athletic_stats' => "2023-24: 22 G League games, 6.1 PPG, 4.3 APG, 2.1 RPG\n2022-23: 28 G League games, 8.2 PPG, 5.1 APG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Shooting Guard',
            'secondary_position' => 'Point Guard',
            'dominant_side' => 'left',
            'club' => 'Philadelphia 76ers NXT',
            'player_jersey' => '0',
            'height' => '196',
            'weight' => '92',
            'location' => 'Philadelphia, Pennsylvania',
            'date_of_birth' => '2001-03-25',
            'school' => 'Villanova University',
            'graduation_year' => '2022',
            'gpa' => '3.8/4.0',
            'awards' => "Big East Player of the Year 2021-22\nNIT Championship MVP 2022\nUSA Basketball U-19 World Cup Silver 2019",
            'recruiting_status' => 'open',
            'sat_act_scores' => 'SAT: 1520',
            'preferred_location' => 'Philadelphia, New York, Boston, Washington DC',
            'competition_level' => 'NBA G League, Summer League',
            'recruiting_info' => 'Highly recruited prospect who chose Villanova for development. Draft eligibility in 2024. Elite scorer with pull-up game and basketball intelligence.',
            'athletic_stats' => "2023-24: 31 games, 17.3 PPG, 4.2 APG, 5.5 RPG\n2022-23: 33 games, 19.8 PPG, 4.8 APG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Small Forward',
            'secondary_position' => 'Shooting Guard',
            'dominant_side' => 'right',
            'club' => 'Toronto Raptors 905',
            'player_jersey' => '25',
            'height' => '200',
            'weight' => '99',
            'location' => 'Toronto, Ontario',
            'date_of_birth' => '2002-07-08',
            'school' => 'University of Toronto',
            'graduation_year' => '2024',
            'gpa' => '3.7/4.0',
            'awards' => "Oregon Tech Conference Player of the Year 2023\nNCAA Summer League All-Star 2023\nCanada Basketball Male Athlete of the Year 2023",
            'recruiting_status' => 'open',
            'sat_act_scores' => 'SAT: 1480',
            'preferred_location' => 'Toronto, Vancouver, Seattle, Denver',
            'competition_level' => 'NBA G League, NCAA, Canadian National Team',
            'recruiting_info' => 'Canadian national team member. Averaging 18.5 PPG with Raptors 905. NBA Draft eligibility 2025. Strong two-way player with excellent court vision.',
            'athletic_stats' => "2023-24: 26 games, 18.5 PPG, 5.1 APG, 6.3 RPG\n2022-23: 22 games, 14.8 PPG, 4.2 APG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Power Forward',
            'secondary_position' => 'Centre',
            'dominant_side' => 'right',
            'club' => 'Detroit Pistons Performance',
            'player_jersey' => '33',
            'height' => '206',
            'weight' => '108',
            'location' => 'Detroit, Michigan',
            'date_of_birth' => '2000-11-12',
            'school' => 'University of Michigan',
            'graduation_year' => '2022',
            'gpa' => '3.6/4.0',
            'awards' => "Big Ten All-Fifth Team 2021-22\nUniversity Records Holder – Rebounding\nAcademic All-Big Ten 2022",
            'recruiting_status' => 'open',
            'preferred_location' => 'Detroit, Chicago, Cleveland, Indianapolis',
            'competition_level' => 'NBA G League, Summer League',
            'recruiting_info' => 'Highly productive college rebounder (11.2 RPG). Drafted in 2nd round (38th overall). Strong fundamentals and basketball IQ. Seeking regular rotation minutes.',
            'athletic_stats' => "2023-24: 24 games, 8.2 PPG, 7.8 RPG, 1.5 BPG\n2022-23: 26 games, 9.1 PPG, 8.2 RPG",
        ]
    ],
    [
        'id' => null,
        'data' => [
            'sport' => 'Basketball',
            'playing_position' => 'Center',
            'secondary_position' => 'Power Forward',
            'dominant_side' => 'right',
            'club' => 'San Antonio Spurs Academies',
            'player_jersey' => '5',
            'height' => '213',
            'weight' => '120',
            'location' => 'Austin, Texas',
            'date_of_birth' => '2003-12-05',
            'school' => 'University of Texas at Austin',
            'graduation_year' => '2025',
            'gpa' => '3.9/4.0',
            'awards' => "Big 12 All-Freshman Team 2022-23\nAll-America Third Team (AP) 2024\nNBA Draft Combine Invitee 2025",
            'recruiting_status' => 'open',
            'sat_act_scores' => 'SAT: 1510',
            'preferred_location' => 'Austin, Dallas, Houston, Phoenix',
            'competition_level' => 'NCAA Division I, NBA G League',
            'recruiting_info' => 'Elite 7-footer with NBA-ready skills. Double-double machine with averages of 14.2 PPG and 11.8 RPG. Projected lottery pick. Excellent post scorer.',
            'athletic_stats' => "2023-24: 32 games, 14.2 PPG, 11.8 RPG, 2.1 BPG",
        ]
    ],
];

echo "Creating " . count($players) . " basketball player profiles...\n\n";

$created = 0;
$errors = [];

foreach ($players as &$player) {
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

        // Generate English name for basketball player
        $names = ['Ethan Carter', 'Marcus Williams', 'Jordan Hayes', 'Aiden Brooks', 'Logan Foster',
                  'Elias Morgan', 'Carson Reed', 'Dylan Shaw', 'Miles Gray', 'Noah Bennett'];
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