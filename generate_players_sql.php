<?php
/**
 * Generate SQL to insert dummy player profiles
 * Run: php generate_players_sql.php > players.sql
 */

$players = [
    ['id' => 21, 'name' => 'Arjun Patel', 'sport' => 'cricket', 'position' => 'All-rounder', 'secondary' => 'Right-arm Medium Bowler', 'dom' => 'right', 'height' => 178, 'weight' => 72, 'jersey' => '7', 'club' => 'Mumbai Cricket Academy', 'location' => 'Mumbai, Maharashtra', 'dob' => '2002-03-15', 'school' => "St. Xavier's College, Mumbai", 'grad' => '2024', 'gpa' => '8.5/10.0', 'awards' => 'Best All-rounder - Inter-College Tournament 2023', 'status' => 'open', 'pref' => 'Mumbai, Pune, Bangalore', 'level' => 'Domestic First Class, IPL'],
    ['id' => 22, 'name' => 'Priya Sharma', 'sport' => 'cricket', 'position' => 'Opening Batter', 'secondary' => 'Wicketkeeper', 'dom' => 'right', 'height' => 165, 'weight' => 58, 'jersey' => '18', 'club' => "Delhi Womens Cricket Academy", 'location' => 'New Delhi', 'dob' => '2003-07-22', 'school' => 'Lady Shri Ram College, Delhi', 'grad' => '2025', 'gpa' => '8.8/10.0', 'awards' => 'Player of the Tournament - Senior Womens T20 Trophy 2023', 'status' => 'open', 'pref' => 'Delhi, Mumbai, Bangalore', 'level' => 'WPL, India A'],
    ['id' => 23, 'name' => 'Rahul Singh', 'sport' => 'football', 'position' => 'Striker', 'secondary' => 'Left Winger', 'dom' => 'left', 'height' => 182, 'weight' => 76, 'jersey' => '9', 'club' => 'Bengaluru FC Reserves', 'location' => 'Bengaluru, Karnataka', 'dob' => '2001-11-08', 'school' => 'Christ University, Bengaluru', 'grad' => '2023', 'gpa' => '3.7/4.0', 'awards' => 'Top Scorer - I-League 2nd Division 2023-24 (14 goals)', 'status' => 'committed', 'pref' => 'Bengaluru, Chennai, Goa', 'level' => 'ISL, I-League'],
    ['id' => 24, 'name' => 'Ananya Reddy', 'sport' => 'football', 'position' => 'Central Midfielder', 'secondary' => 'Defensive Midfielder', 'dom' => 'right', 'height' => 170, 'weight' => 62, 'jersey' => '8', 'club' => 'Gokulam Kerala FC Women', 'location' => 'Hyderabad, Telangana', 'dob' => '2002-05-12', 'school' => 'University of Hyderabad', 'grad' => '2024', 'gpa' => '3.9/4.0', 'awards' => 'Indian Womens League Winner 2023-24', 'status' => 'open', 'pref' => 'Kerala, Tamil Nadu, Maharashtra', 'level' => 'IWL, India Senior Team'],
    ['id' => 25, 'name' => 'Vikram Choudhary', 'sport' => 'cricket', 'position' => 'Fast Bowler', 'secondary' => 'Lower Order Batter', 'dom' => 'right', 'height' => 190, 'weight' => 85, 'jersey' => '99', 'club' => 'Rajasthan Royals Academy', 'location' => 'Jaipur, Rajasthan', 'dob' => '2000-09-30', 'school' => 'Manipal University, Jaipur', 'grad' => '2022', 'gpa' => '7.2/10.0', 'awards' => 'Purple Cap - Syed Mushtaq Ali Trophy 2023 (18 wickets)', 'status' => 'open', 'pref' => 'Rajasthan, Gujarat, Mumbai', 'level' => 'IPL, Ranji Trophy'],
    ['id' => 26, 'name' => 'Meera Joshi', 'sport' => 'cricket', 'position' => 'Leg Spinner', 'secondary' => 'Middle Order Batter', 'dom' => 'right', 'height' => 162, 'weight' => 55, 'jersey' => '23', 'club' => "Punjab Womens Cricket Association", 'location' => 'Chandigarh, Punjab', 'dob' => '2004-01-18', 'school' => 'Panjab University, Chandigarh', 'grad' => '2026', 'gpa' => '8.2/10.0', 'awards' => 'Best Bowler - U-19 Womens T20 World Cup 2023', 'status' => 'open', 'pref' => 'Chandigarh, Mohali, Delhi', 'level' => 'WPL, India U-19'],
    ['id' => 27, 'name' => 'Karan Malhotra', 'sport' => 'football', 'position' => 'Centre Back', 'secondary' => 'Right Back', 'dom' => 'right', 'height' => 188, 'weight' => 82, 'jersey' => '4', 'club' => 'Mohun Bagan SG Reserves', 'location' => 'Kolkata, West Bengal', 'dob' => '2001-02-28', 'school' => 'Jadavpur University, Kolkata', 'grad' => '2023', 'gpa' => '3.5/4.0', 'awards' => 'Best Defender - Calcutta Football League 2023', 'status' => 'committed', 'pref' => 'Kolkata, Mumbai, Goa', 'level' => 'ISL, I-League'],
    ['id' => 28, 'name' => 'Aditya Nair', 'sport' => 'football', 'position' => 'Goalkeeper', 'secondary' => '', 'dom' => 'right', 'height' => 175, 'weight' => 68, 'jersey' => '1', 'club' => 'Sethu FC', 'location' => 'Chennai, Tamil Nadu', 'dob' => '2002-08-14', 'school' => 'Anna University, Chennai', 'grad' => '2024', 'gpa' => '3.8/4.0', 'awards' => 'Golden Glove - Indian Womens League 2023-24', 'status' => 'open', 'pref' => 'Chennai, Bengaluru, Kerala', 'level' => 'IWL, India Senior Team'],
    ['id' => 29, 'name' => 'Kavya Iyer', 'sport' => 'football', 'position' => 'Left Back', 'secondary' => '', 'dom' => 'left', 'height' => 172, 'weight' => 68, 'jersey' => '3', 'club' => 'Chennai City FC', 'location' => 'Chennai, Tamil Nadu', 'dob' => '2000-06-21', 'school' => 'Saveetha Institute of Medical and Technology', 'grad' => '2022', 'gpa' => '8.1/10.0', 'awards' => 'Player of the Year - Tamil Nadu State League 2022-23', 'status' => 'closed', 'pref' => 'Tamil Nadu, Kerala, Karnataka', 'level' => 'ISL, I-League'],
    ['id' => 30, 'name' => 'Sneha Verma', 'sport' => 'football', 'position' => 'Forward', 'secondary' => 'Right Wing', 'dom' => 'right', 'height' => 180, 'weight' => 74, 'jersey' => '11', 'club' => 'Odisha FC', 'location' => 'Bhubaneswar, Odisha', 'dob' => '2002-04-12', 'school' => 'Utkal University, Bhubaneswar', 'grad' => '2024', 'gpa' => '3.6/4.0', 'awards' => 'ISL Top Scorer - 2023-24 (12 goals)', 'status' => 'open', 'pref' => 'Odisha, West Bengal, Assam', 'level' => 'ISL, I-League'],
];

foreach ($players as $p) {
    $slug = strtolower(str_replace(' ', '-', $p['name']));
    echo "-- {$p['name']} (ID {$p['id']}) - " . ucfirst($p['sport']) . " {$p['position']}\n";
    echo "INSERT INTO wp_posts (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type) VALUES\n";
    echo "({$p['id']}, 1, NOW(), NOW(), '<p>Professional {$p['sport']} player from {$p['location']}.</p>', '{$p['name']}', 'publish', 'open', 'open', '', '{$slug}', '', '', NOW(), NOW(), '', 0, '', 0, 'player');\n\n";

    $secondary = addslashes($p['secondary']);
    $club = addslashes($p['club']);
    $school = addslashes($p['school']);
    $awards = addslashes($p['awards']);

    echo "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES\n";
    echo "({$p['id']}, 'sport', '{$p['sport']}'),\n";
    echo "({$p['id']}, 'playing_position', '{$p['position']}'),\n";
    echo "({$p['id']}, 'secondary_position', '{$secondary}'),\n";
    echo "({$p['id']}, 'dominant_side', '{$p['dom']}'),\n";
    echo "({$p['id']}, 'club', '{$club}'),\n";
    echo "({$p['id']}, 'player_jersey', '{$p['jersey']}'),\n";
    echo "({$p['id']}, 'height', '{$p['height']}'),\n";
    echo "({$p['id']}, 'weight', '{$p['weight']}'),\n";
    echo "({$p['id']}, 'location', '{$p['location']}'),\n";
    echo "({$p['id']}, 'date_of_birth', '{$p['dob']}'),\n";
    echo "({$p['id']}, 'school', '{$school}'),\n";
    echo "({$p['id']}, 'graduation_year', '{$p['grad']}'),\n";
    echo "({$p['id']}, 'gpa', '{$p['gpa']}'),\n";
    echo "({$p['id']}, 'awards', '{$awards}'),\n";
    echo "({$p['id']}, 'recruiting_status', '{$p['status']}'),\n";
    echo "({$p['id']}, 'preferred_location', '{$p['pref']}'),\n";
    echo "({$p['id']}, 'competition_level', '{$p['level']}');\n\n";
}
echo "\n-- Done! Run: mysql -u root -proot local < players.sql\n";
?>
EOF