-- Update post titles to real player names
UPDATE wp_posts SET post_title = 'Arjun Patel' WHERE ID = 21 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Priya Sharma' WHERE ID = 22 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Rahul Singh' WHERE ID = 23 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Ananya Reddy' WHERE ID = 24 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Vikram Choudhary' WHERE ID = 25 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Meera Joshi' WHERE ID = 26 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Karan Malhotra' WHERE ID = 27 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Aditya Nair' WHERE ID = 28 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Kavya Iyer' WHERE ID = 29 AND post_type = 'player';
UPDATE wp_posts SET post_title = 'Sneha Verma' WHERE ID = 30 AND post_type = 'player';

-- Set post_name (slug)
UPDATE wp_posts SET post_name = 'arjun-patel' WHERE ID = 21;
UPDATE wp_posts SET post_name = 'priya-sharma' WHERE ID = 22;
UPDATE wp_posts SET post_name = 'rahul-singh' WHERE ID = 23;
UPDATE wp_posts SET post_name = 'ananya-reddy' WHERE ID = 24;
UPDATE wp_posts SET post_name = 'vikram-choudhary' WHERE ID = 25;
UPDATE wp_posts SET post_name = 'meera-joshi' WHERE ID = 26;
UPDATE wp_posts SET post_name = 'karan-malhotra' WHERE ID = 27;
UPDATE wp_posts SET post_name = 'aditya-nair' WHERE ID = 28;
UPDATE wp_posts SET post_name = 'kavya-iyer' WHERE ID = 29;
UPDATE wp_posts SET post_name = 'sneha-verma' WHERE ID = 30;
