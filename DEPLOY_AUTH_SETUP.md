# Deployment Authentication Setup Guide

This guide helps you set up authentication so your remote server can pull from the private GitHub repository.

## Problem
When you changed the GitHub repository to private, the remote server lost access because it doesn't have authentication credentials.

## Solution: Deploy Keys (Recommended)

Deploy keys are SSH keys specifically for deployment. They provide read-only access to a single repository.

### Step 1: Generate SSH Key on Remote Server

SSH into your remote server and run:

```bash
ssh-keygen -t ed25519 -C "giftsaround-deploy-key" -f ~/.ssh/giftsaround_deploy
```

Press Enter when asked for a passphrase (leave it empty for automated deployments).

### Step 2: Copy the Public Key

```bash
cat ~/.ssh/giftsaround_deploy.pub
```

Copy the entire output (starts with `ssh-ed25519`).

### Step 3: Add Deploy Key to GitHub

1. Go to: https://github.com/alsessions/giftsaround/settings/keys
2. Click "Add deploy key"
3. Title: "Production Server Deploy Key"
4. Key: Paste the public key from Step 2
5. **Leave "Allow write access" unchecked** (read-only is safer)
6. Click "Add key"

### Step 4: Configure SSH on Remote Server

Create/edit the SSH config file:

```bash
nano ~/.ssh/config
```

Add this configuration:

```
Host github.com-giftsaround
    HostName github.com
    User git
    IdentityFile ~/.ssh/giftsaround_deploy
    IdentitiesOnly yes
```

Save and exit (Ctrl+O, Enter, Ctrl+X).

Set proper permissions:

```bash
chmod 600 ~/.ssh/config
chmod 600 ~/.ssh/giftsaround_deploy
```

### Step 5: Update Git Remote URL

In your project directory on the remote server:

```bash
cd /path/to/your/project
git remote set-url origin git@github.com-giftsaround:alsessions/giftsaround.git
```

Note the special hostname `github.com-giftsaround` - this matches the Host in your SSH config.

### Step 6: Test the Connection

```bash
ssh -T git@github.com-giftsaround
```

You should see: `Hi alsessions/giftsaround! You've successfully authenticated...`

### Step 7: Test Git Pull

```bash
git pull origin main
```

If successful, your deploy script will now work!

## Alternative: Personal Access Token (HTTPS)

If you prefer HTTPS or have issues with SSH:

### Step 1: Create Personal Access Token

1. Go to: https://github.com/settings/tokens
2. Click "Generate new token" → "Generate new token (classic)"
3. Name: "Gifts Around Production Server"
4. Expiration: Choose appropriate duration
5. Scopes: Check **only** `repo`
6. Click "Generate token"
7. **Copy the token immediately** (you won't see it again)

### Step 2: Update Remote URL

On your remote server:

```bash
cd /path/to/your/project
git remote set-url origin https://alsessions:YOUR_TOKEN_HERE@github.com/alsessions/giftsaround.git
```

Replace `YOUR_TOKEN_HERE` with your actual token.

### Step 3: Test

```bash
git pull origin main
```

**Security Note**: The token will be stored in `.git/config`. Make sure your file permissions are secure.

## Troubleshooting

### "Permission denied (publickey)"
- The SSH key isn't configured correctly
- Verify the deploy key is added to GitHub
- Check SSH config file syntax
- Ensure correct permissions on SSH files

### "Repository not found"
- The deploy key might not be added to the correct repository
- Verify you're using the right GitHub account
- Check the remote URL: `git remote -v`

### "Could not resolve hostname"
- Check SSH config Host name matches the remote URL
- Verify DNS/network connectivity

### Deploy Script Still Fails
- Run commands manually to isolate the issue
- Check that you're in the correct directory
- Verify the user running the script has access to SSH keys

## Security Best Practices

1. ✅ Use deploy keys instead of personal SSH keys on servers
2. ✅ Use read-only deploy keys (no write access needed)
3. ✅ Rotate tokens/keys periodically
4. ✅ Use separate keys for each server/environment
5. ✅ Never commit tokens or private keys to the repository
6. ❌ Don't use personal credentials on shared servers
7. ❌ Don't share SSH keys between developers

## Testing Your Deploy Script

Once authentication is set up:

```bash
cd /path/to/your/project
chmod +x deploy.sh
./deploy.sh
```

The script should now successfully pull from the private repository and complete the deployment.