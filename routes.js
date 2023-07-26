const express = require('express');
const router = express.Router();
const passport = require('passport');
const authController = require('./controllers/authController');

// GET login page
router.get('/login', authController.getLoginPage);

// GET logout
router.get('/logout', authController.logout);

// GET dashboard
router.get('/dashboard', authController.dashboard);

// Discord authentication route
router.get('/auth/discord', passport.authenticate('discord', { scope: ['identify', 'email'] }));

// Discord authentication callback
router.get('/auth/discord/callback', passport.authenticate('discord', { failureRedirect: '/login' }), authController.discordLoginCallback);

module.exports = router;
