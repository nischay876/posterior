const pool = require('../db');
const config = require('../config');

// GET login page
exports.getLoginPage = (req, res) => {
    res.render('login');
};

// Dashboard
exports.dashboard = async (req, res) => {
    try {
        if (req.user) {
            const user = req.user;
            const unid = user.unid; // Assuming the user object has an 'id' property representing the user's I
            const query = `
          SELECT id, data
          FROM ass
          WHERE jsonb_extract_path_text(data, 'uploader') = $1
          AND jsonb_extract_path_text(data, 'is', 'image') = 'true'
          ORDER BY jsonb_extract_path_text(data, 'timestamp') DESC;
        `;
            const { rows } = await pool.query(query, [unid]);
            const images = rows;

            res.render('dashboard', { user, images, ass_domain: config.ass_domain });
        } else {
            res.redirect('/login');
        }
    } catch (error) {
        console.error(error);
        res.redirect('/login');
    }
};

// GET Discord 
exports.discordLoginCallback = (req, res) => {
    // The user is authenticated through Discord OAuth at this point
    // Redirect the user to the dashboard after successful authentication
    console.log('Discord login successful', req.user.email);
    //console.log('User data:', req.user);
    res.redirect('/dashboard');
};


// GET logout
exports.logout = (req, res) => {
    req.session.destroy((err) => {
        if (err) {
            console.error(err);
        }
        res.redirect('/login');
    });
};
