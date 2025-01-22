## Setting Up Credentials

1. Copy the `credentials.example.php` file and rename it to `credentials.php`.
2. Open the `credentials.php` file and replace the placeholders with your actual credentials:
   - `CLIENT_ID`: Your OAuth client ID from the Google Developer Console.
   - `CLIENT_SECRET`: Your OAuth client secret from the Google Developer Console.
   - `REFRESH_TOKEN`: The refresh token you get after completing the OAuth flow.
3. **Make sure to use the correct OAuth scopes** for the features you need. Without the right scopes, some features may not work properly. For example, to access Google Drive, you’ll need the Drive API scope.

That's it! Now your credentials are set up and ready to go.
