-- Update post titles for the 10 new players
UPDATE wp_posts SET post_title = 'Arjun Patel', post_status = 'publish', post_content = '<p>Professional cricket player with exceptional all-round abilities from Mumbai, Maharashtra.</p>' WHERE ID = 21 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Priya Sharma', post_status = 'publish', post_content = '<p>Left-handed opening batter and wicketkeeper from New Delhi.</p>' WHERE ID = 22 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Rahul Singh', post_status = 'publish', post_content = '<p>Clinical striker with excellent finishing skills from Bengaluru.</p>' WHERE ID = 23 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Ananya Reddy', post_status = 'publish', post_content = '<p>Dynamic midfielder with box-to-box energy from Hyderabad.</p>' WHERE ID = 24 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Vikram Choudhary', post_status = 'publish', post_content = '<p>Express pace bowler with deadly yorkers from Jaipur.</p>' WHERE ID = 25 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Meera Joshi', post_status = 'publish', post_content = '<p>Legspinner with deceptive flight control from Chandigarh.</p>' WHERE ID = 26 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Karan Malhotra', post_status = 'publish', post_content = '<p>Centre back with strong aerial presence from Kolkata.</p>' WHERE ID = 27 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Aditya Nair', post_status = 'publish', post_content = '<p>Goalkeeper with excellent reflexes from Chennai.</p>' WHERE ID = 28 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Kavya Iyer', post_status = 'publish', post_content = '<p>Left-back with attacking runs from Chennai.</p>' WHERE ID = 29 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Sneha Verma', post_status = 'publish', post_content = '<p>Aggressive forward with explosive power hitting from Bhubaneswar.</p>' WHERE ID = 30 AND post_type = 'player';
echo "SQL statements created"
