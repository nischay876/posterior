const db = require('../db');
const axios = require('axios');
const crypto = require("crypto");
const config = require('../config');

exports.create = async (email, password, name) => {
    const query = 'INSERT INTO users1 (email, password, name) VALUES ($1, $2, $3) RETURNING *';
    const values = [email, password, name];
    const result = await db.query(query, values);
    return result.rows[0];
};

exports.findByEmail = async (email) => {
    const query = 'SELECT * FROM users1 WHERE email = $1';
    const values = [email];
    const result = await db.query(query, values);
    return result.rows[0];
};

exports.findById = async (id) => {
    const query = 'SELECT * FROM users1 WHERE id = $1';
    const values = [id];
    const result = await db.query(query, values);
    return result.rows[0];
};

exports.updateApiKeySql = async (api_key, verification_code, email) => {
    const query = 'UPDATE users1 SET api_key = $1 WHERE verification_code = $2 AND email = $3';
    const values = [api_key, verification_code, email];
    const result = await db.query(query, values);
    return result.rows[0];
};

exports.CheckVerificationCode = async (code, email) => {
    const query = 'SELECT * FROM users1 WHERE verification_code = $1 AND email = $2';
    const values = [code, email];
    const result = await db.query(query, values);
    return result.rows[0];
};

exports.findOrCreateByDiscordId = async (discordId, email, username) => {
    try {
        // First, try to find the user by their Discord ID
        let user = await findByDiscordId(discordId);

        if (!user) {
            // Create User on ASS
            var randompassword = crypto.randomBytes(32).toString('hex');
            const userData = {
                username: username,
                password: randompassword
            };
            const apiUrl = config.ass_domain + '/api/user/'; // Replace with your API endpoint URL
            const response = await axios.post(apiUrl, userData, {
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': config.ass_admin_token
                },
            });
            const responseData = response.data;
            const api_key = responseData.token;
            // If the user does not exist, create a new user with the Discord ID
            const query = 'INSERT INTO users1 (discord_id, email, username, api_key, unid) VALUES ($1, $2, $3, $4, $5) RETURNING *';
            const values = [discordId, email, username, api_key, responseData.unid];
            const result = await db.query(query, values);
            user = result.rows[0];
        }
        return user;
    } catch (error) {
        throw error;
    }
};

const findByDiscordId = async (discordId) => {
    const query = 'SELECT * FROM users1 WHERE discord_id = $1';
    const values = [discordId];
    const result = await db.query(query, values);
    return result.rows[0];
};
