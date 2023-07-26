const express = require('express');
const app = express();
const bodyParser = require('body-parser');
const session = require('express-session');
const passport = require('passport');
const DiscordStrategy = require('passport-discord').Strategy;
const config = require('./config');
const routes = require('./routes');
const userModel = require('./models/userModel');
const db = require('./db'); 
app.use('/assets', express.static('assets'))
// Set EJS as the template engine
app.set('view engine', 'ejs');

// Set up middlewares
app.use(bodyParser.urlencoded({ extended: false }));
app.use(bodyParser.json());
app.use(session({
    secret: config.sessionSecret,
    resave: false,
    saveUninitialized: true,
    cookie: {
        maxAge: 7 * 24 * 60 * 60 * 1000, // 7 days (in milliseconds)
    },
}));

app.use(passport.initialize());
app.use(passport.session());

// Discord authentication setup
passport.use(
    new DiscordStrategy(
        {
            clientID: config.discord.clientID,
            clientSecret: config.discord.clientSecret,
            callbackURL: config.discord.callbackURL,
            scope: ['identify', 'email'],
        },
        (accessToken, refreshToken, profile, done) => {
            // This function is called when a user authorizes the Discord application
            // You can use the 'profile' object to access user details like profile.id, profile.username, profile.email, etc.
            // Implement logic to find or create a user in the database based on the 'profile' information
            // For simplicity, let's assume we have a userModel.findOrCreateByDiscordId() function

            userModel.findOrCreateByDiscordId(profile.id, profile.email, profile.username + profile.discriminator)
                .then((user) => {
                    return done(null, user);
                })
                .catch((err) => {
                    return done(err);
                });
        }
    )
);

// Serialize and deserialize user for session management
passport.serializeUser((user, done) => {
    done(null, user.id);
});

passport.deserializeUser(async (id, done) => {
    try {
        const user = await userModel.findById(id);
        done(null, user);
    } catch (error) {
        done(error);
    }
});

// Set up routes
app.use('/', routes);

// Start the server
const port = 3000;
async function startServer() {
    try {
        // Check if the "users1" table exists
        const result = await db.query('SELECT to_regclass(\'public.users1\') as table_exists');
        if (result.rows[0].table_exists === null) {
            // Table does not exist, create it
            await createUsersTable();
            console.log('Table "users1" created.');
        }

        // Start the Express server
        app.listen(port, () => {
            console.log(`Server is running on http://localhost:${port}`);
        });
    } catch (error) {
        console.error('Error checking/creating "users1" table:', error);
        // Handle the error appropriately, such as exiting the server or showing an error message.
    }
}
startServer();
async function createUsersTable() {
    const createTableQuery = `
      CREATE TABLE IF NOT EXISTS "users1" (
        "id" SERIAL PRIMARY KEY,
        "name" VARCHAR(100),
        "email" VARCHAR(100) NOT NULL,
        "password" VARCHAR(100),
        "discord_id" VARCHAR(100),
        "username" VARCHAR(100),
        "api_key" VARCHAR,
        "verification_code" VARCHAR,
        "is_verified" VARCHAR,
        "unid" VARCHAR
      );
    `;
  
    try {
      await db.query(createTableQuery);
    } catch (error) {
      console.error('Error creating "users1" table:', error);
      // Handle the error appropriately, such as throwing it or logging it.
      throw error;
    }
  }