export default class WalletConnect {
    constructor() {
        this.account = null;
        this.provider = null;
        this.isConnected = false;

        // API endpoints
        this.apiEndpoints = {
            connect: '/app/api/wallet/connect',
            disconnect: '/app/api/wallet/disconnect',
            refresh: '/app/api/wallet/refresh-ens',
            syncState: '/app/api/wallet/sync-state'
        };

        // DOM Elements
        this.connectButton = document.getElementById('connectWalletBtn');
        this.walletInfo = document.getElementById('walletInfo');
        this.accountText = document.getElementById('accountAddress');
        this.networkText = document.getElementById('networkName');
        this.balanceText = document.getElementById('accountBalance');
        this.refreshButton = null;

        // Optional ENS elements
        this.avatarElement = document.getElementById('ensAvatar');
        this.ensNameElement = document.getElementById('ensName');
        this.twitterElement = document.getElementById('twitterLink');
        this.githubElement = document.getElementById('githubLink');
        this.urlElement = document.getElementById('urlLink');

        // Add polling interval (in milliseconds)
        this.pollInterval = 5000; // 5 seconds
        this.pollTimer = null;

        this.init();

        console.log('API Endpoints:', {
            connect: this.apiEndpoints.connect,
            disconnect: this.apiEndpoints.disconnect,
            refresh: this.apiEndpoints.refresh
        });
    }

    init() {
        // Check if MetaMask is installed
        if (typeof window.ethereum !== 'undefined') {
            this.provider = window.ethereum;
            this.setupEventListeners();
            this.checkConnection();
            this.checkForExternalDisconnection();

            // Only start polling if we have a stored address
            if (localStorage.getItem('lastConnectedWalletAddress')) {
                this.startConnectionPolling();
            }
        } else {
            this.connectButton.textContent = 'Please install MetaMask';
            this.connectButton.disabled = true;
        }
    }

    startConnectionPolling() {
        // Clear any existing timer
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
        }

        // Start polling
        this.pollTimer = setInterval(async () => {
            if (!this.provider) return;

            try {
                const accounts = await this.provider.request({ method: 'eth_accounts' });
                const isCurrentlyConnected = accounts.length > 0;
                const storedAddress = localStorage.getItem('lastConnectedWalletAddress');

                // If we think we're connected but MetaMask says otherwise
                if (storedAddress && !isCurrentlyConnected) {
                    console.log('Wallet disconnection detected by polling');
                    await this.handleMetaMaskDisconnect();
                }
                // Or if the account changed without triggering accountsChanged
                else if (storedAddress && isCurrentlyConnected &&
                    accounts[0].toLowerCase() !== storedAddress.toLowerCase()) {
                    console.log('Account change detected by polling');
                    await this.handleAccountsChanged(accounts);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, this.pollInterval);
    }

    stopConnectionPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    // Add cleanup to existing disconnect handler
    async handleMetaMaskDisconnect() {
        const previousAccount = this.account;

        // Clear local state
        this.account = null;
        this.isConnected = false;
        localStorage.removeItem('lastConnectedWalletAddress');

        // Stop polling when disconnected
        this.stopConnectionPolling();

        if (previousAccount) {
            try {
                await fetch(this.apiEndpoints.disconnect, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        address: previousAccount
                    })
                });
            } catch (error) {
                console.error('Backend disconnection error:', error);
            }
        }

        this.updateUI();
    }


    setupEventListeners() {
        // Connect button click handler
        this.connectButton?.addEventListener('click', () => this.connectWallet());

        // Setup MetaMask event listeners
        this.provider.on('accountsChanged', (accounts) => {
            if (accounts.length === 0) {
                this.handleMetaMaskDisconnect();
            } else {
                this.handleAccountsChanged(accounts);
            }
        });

        this.provider.on('chainChanged', () => window.location.reload());
        this.provider.on('disconnect', () => this.handleMetaMaskDisconnect());

        window.addEventListener('unload', () => {
            this.stopConnectionPolling();
        });
    }

    async connectWallet() {
        try {
            // Request with specific configuration to limit selection
            const accounts = await this.provider.request({
                method: 'eth_requestAccounts',
                params: [{
                    eth_accounts: {
                        maxCount: 1 // Limit to 1 account
                    }
                }]
            });

            // The rest stays the same but we don't need the array handling anymore
            const formData = new FormData();
            formData.append('address', accounts[0]);

            const response = await fetch(this.apiEndpoints.connect, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const rawData = await response.text();
            console.log('=== START OF RAW RESPONSE ===');
            console.log(rawData);
            console.log('=== END OF RAW RESPONSE ===');

            let jsonData;
            try {
                jsonData = JSON.parse(rawData);
                this.handleAccountsChanged([accounts[0]]);
            } catch (parseError) {
                console.error('=== PARSE ERROR ===');
                console.error('Error type:', parseError.name);
                console.error('Error message:', parseError.message);
                throw new Error('Failed to parse server response');
            }
        } catch (error) {
            console.error('Error connecting wallet:', error);
            alert('Failed to connect wallet: ' + error.message);
        }
    }

    // async connectWallet() {
    //     try {
    //         const accounts = await this.provider.request({ method: 'eth_requestAccounts' });
    //         this.handleAccountsChanged(accounts);
    //     } catch (error) {
    //         console.error('Error connecting wallet:', error);
    //         alert('Failed to connect wallet: ' + error.message);
    //     }
    // }

    async handleAccountsChanged(accounts) {
        console.group('handleAccountsChanged');
        console.log('Called with accounts:', accounts);

        if (accounts.length === 0) {
            console.log('No accounts found, handling disconnect');
            await this.handleMetaMaskDisconnect();
            console.groupEnd();
            return;
        }

        try {
            console.log('Account found:', accounts[0]);
            this.account = accounts[0];
            this.isConnected = true;

            localStorage.setItem('lastConnectedWalletAddress', this.account);
            console.log('Saved address to localStorage');

            await this.updateWalletInfo();
            console.log('Wallet info updated');

            // Backend connection
            console.group('Backend Connection');
            console.log('Preparing request for address:', this.account);

            const formData = new FormData();
            formData.append('address', this.account);

            console.log('Request details:', {
                url: this.apiEndpoints.connect,
                method: 'POST',
                formData: Object.fromEntries(formData.entries())
            });

            const response = await fetch(this.apiEndpoints.connect, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            console.log('Response details:', {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries()),
                url: response.url
            });

            const rawData = await response.text();
            console.log('=== START OF RAW RESPONSE ===');
            console.log(rawData);
            console.log('=== END OF RAW RESPONSE ===');

            let jsonData;
            try {
                jsonData = JSON.parse(rawData);
                console.log('Parsed JSON:', jsonData);
            } catch (parseError) {
                console.error('=== PARSE ERROR ===');
                console.error('Error type:', parseError.name);
                console.error('Error message:', parseError.message);
                console.error('=== FULL RESPONSE THAT FAILED TO PARSE ===');
                document.body.innerHTML += `<pre style="position:fixed;top:0;left:0;right:0;bottom:0;background:white;z-index:999999;overflow:auto;">${rawData}</pre>`;
                throw new Error('Failed to parse server response - check console and page for full output');
            }

            if (!response.ok) {
                console.error('=== ERROR RESPONSE ===');
                console.error('Raw response:', rawData);
                throw new Error('Server returned error status');
            }

            this.updateUIWithBackendData(jsonData);
            console.log('UI updated with backend data');
            console.groupEnd();
            this.startConnectionPolling();

            this.updateUI();
            console.log('Main UI update complete');

        } catch (error) {
            console.error('=== FULL ERROR DETAILS ===');
            console.error(error);
            alert('Connection failed - Check console and page for full details');
        }

        console.groupEnd();
    }

    async checkForExternalDisconnection() {
        const lastConnectedAddress = localStorage.getItem('lastConnectedWalletAddress');

        if (lastConnectedAddress) {
            try {
                const accounts = await this.provider.request({ method: 'eth_accounts' });

                if (accounts.length === 0 || accounts[0].toLowerCase() !== lastConnectedAddress.toLowerCase()) {
                    // MetaMask was disconnected while we were away
                    await this.handleMetaMaskDisconnect();
                }
            } catch (error) {
                console.error('Error checking external disconnection:', error);
            }
        }
    }

    async refreshENSData() {
        if (!this.isConnected) return;

        try {
            const response = await fetch(this.apiEndpoints.refresh, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    address: this.account
                })
            });

            if (!response.ok) {
                throw new Error('Failed to refresh ENS data');
            }

            const data = await response.json();
            this.updateUIWithBackendData(data);
        } catch (error) {
            console.error('Error refreshing ENS data:', error);
            alert('Failed to refresh ENS data: ' + error.message);
        }
    }

    async updateWalletInfo() {
        if (!this.isConnected) return;

        try {
            // Get network name
            const chainId = await this.provider.request({ method: 'eth_chainId' });
            const networkName = this.getNetworkName(chainId);

            // Get balance
            const balance = await this.provider.request({
                method: 'eth_getBalance',
                params: [this.account, 'latest']
            });

            const ethBalance = (parseInt(balance, 16) / 1e18).toFixed(4);

            // Update UI elements
            if (this.networkText) {
                this.networkText.textContent = networkName;
                this.balanceText.textContent = ethBalance;
                this.accountText.textContent = this.formatAddress(this.account);
            }
        } catch (error) {
            console.error('Error updating wallet info:', error);
        }
    }

    updateUIWithBackendData(data) {
        if (!data) return;

        // Update ENS name if available
        if (data.ens_name && this.ensNameElement) {
            this.ensNameElement.textContent = data.ens_name;
            this.accountText.textContent = data.ens_name;
        }

        // Update avatar if available
        if (data.ens_avatar && this.avatarElement) {
            this.avatarElement.src = data.ens_avatar;
            this.avatarElement.style.display = 'block';
        }

        // Update ENS data if available
        if (data.ens_data) {
            const ensData = typeof data.ens_data === 'string'
                ? JSON.parse(data.ens_data)
                : data.ens_data;

            if (this.twitterElement && ensData.twitter) {
                this.twitterElement.href = `https://twitter.com/${ensData.twitter}`;
                this.twitterElement.textContent = `@${ensData.twitter}`;
                this.twitterElement.style.display = 'block';
            }

            if (this.githubElement && ensData.github) {
                this.githubElement.href = `https://github.com/${ensData.github}`;
                this.githubElement.textContent = ensData.github;
                this.githubElement.style.display = 'block';
            }

            if (this.urlElement && ensData.url) {
                this.urlElement.href = ensData.url;
                this.urlElement.textContent = ensData.url;
                this.urlElement.style.display = 'block';
            }
        }
    }

    updateUI() {
        if (!this.connectButton) {
            return;
        }
        if (this.isConnected) {
            this.connectButton.textContent = 'Wallet Connected';
            this.connectButton.disabled = true;
            this.walletInfo.style.display = 'block';

            // Add refresh button if not exists
            if (!this.refreshButton) {
                this.refreshButton = document.createElement('button');
                this.refreshButton.textContent = 'Refresh ENS Data';
                this.refreshButton.className = 'btn btn-secondary ms-2';
                this.refreshButton.onclick = () => this.refreshENSData();
                this.walletInfo.appendChild(this.refreshButton);
            }
        } else {
            this.connectButton.textContent = 'Connect Wallet';
            this.connectButton.disabled = false;
            this.walletInfo.style.display = 'none';

            if (this.refreshButton) {
                this.refreshButton.remove();
                this.refreshButton = null;
            }
        }
    }

    async checkConnection() {
        try {
            const accounts = await this.provider.request({ method: 'eth_accounts' });
            if (accounts.length > 0) {
                this.handleAccountsChanged(accounts);
            }
        } catch (error) {
            console.error('Error checking connection:', error);
        }
    }

    getNetworkName(chainId) {
        const networks = {
            '0x1': 'Ethereum Mainnet',
            '0x3': 'Ropsten Testnet',
            '0x4': 'Rinkeby Testnet',
            '0x5': 'Goerli Testnet',
            '0x2a': 'Kovan Testnet',
            '0x89': 'Polygon Mainnet',
            '0x13881': 'Mumbai Testnet',
            '0xaa36a7': 'Sepolia Testnet'
        };
        return networks[chainId] || `Chain ID: ${chainId}`;
    }

    formatAddress(address) {
        return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
    }
}