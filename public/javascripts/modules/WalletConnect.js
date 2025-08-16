export default class WalletConnect {
    constructor() {
        this.account = null;
        this.provider = null;
        this.isConnected = false;

        // API endpoints
        this.apiEndpoints = {
            connect: '/app/api/wallet/connect',
            disconnect: '/app/api/wallet/disconnect',
            refresh: '/app/api/wallet/refresh-ens'
        };

        // DOM Elements
        this.connectButton = document.getElementById('connectWalletBtn');
        this.refreshButton = document.getElementById('refreshWalletBtn');

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
    }


    setupEventListeners() {
        // Connect button click handler
        this.connectButton?.addEventListener('click', () => this.connectWallet());
        this.refreshButton?.addEventListener('click', () => this.refreshENSData());

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

            const formData = new FormData();
            formData.append('address', accounts[0]);

            const response = await fetch(this.apiEndpoints.connect, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const jsonData = await response.json();

            if (!response.ok) {
                throw new Error(jsonData.error || 'Failed to connect wallet');
            }

            // Store address in localStorage before reload
            localStorage.setItem('lastConnectedWalletAddress', accounts[0]);

            // Reload the page to get fresh UI from backend
            window.location.reload();

        } catch (error) {
            console.error('Error connecting wallet:', error);
            alert('Failed to connect wallet: ' + error.message);
        }
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

            // Reload page to show updated ENS data
            window.location.reload();

        } catch (error) {
            console.error('Error refreshing ENS data:', error);
            alert('Failed to refresh ENS data: ' + error.message);
        }
    }

    async checkConnection() {
        try {
            const accounts = await this.provider.request({ method: 'eth_accounts' });
            if (accounts.length > 0) {
                // Just update the account state without reloading
                this.account = accounts[0];
                this.isConnected = true;
                localStorage.setItem('lastConnectedWalletAddress', this.account);
            }
        } catch (error) {
            console.error('Error checking connection:', error);
        }
    }

    async handleAccountsChanged(accounts) {
        if (accounts.length === 0) {
            await this.handleMetaMaskDisconnect();
            return;
        }

        const currentAccount = accounts[0];
        const storedAddress = localStorage.getItem('lastConnectedWalletAddress');

        // Only update if the account actually changed from what's stored
        if (!storedAddress || storedAddress.toLowerCase() !== currentAccount.toLowerCase()) {
            try {
                const formData = new FormData();
                formData.append('address', currentAccount);

                const response = await fetch(this.apiEndpoints.connect, {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });

                if (response.ok) {
                    localStorage.setItem('lastConnectedWalletAddress', currentAccount);
                    window.location.reload();
                } else {
                    throw new Error('Failed to update wallet connection');
                }
            } catch (error) {
                console.error('Failed to handle account change:', error);
                alert('Failed to update wallet connection: ' + error.message);
            }
        }
    }
}