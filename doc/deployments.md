#   Deployment of the *SIMO Express* website

*   If the db server is installed globally, run src/deploy.sh to
    copy all files from [repo]/public/* into /var/www/html/simo-express

*   If the db server is restricted to the repo, as when using a nix-shell,
    in order to serve the simo-express website you need to save
    symlinks of [repo]/public/* in /var/www/html/simo-express/
