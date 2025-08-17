// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/Ownable.sol";

/// @title CodeQuillRegistry - repo → owner mapping with relayer support
contract CodeQuillRegistry is Ownable {
    /// @notice repoId (bytes32) -> owner (wallet)
    mapping(bytes32 => address) public repoOwner;
    mapping(address => bytes32[]) private reposByOwner;

    event RepoClaimed(bytes32 indexed repoId, address indexed owner, string meta);
    event RepoTransferred(bytes32 indexed repoId, address indexed oldOwner, address indexed newOwner);

    constructor(address initialOwner) Ownable(initialOwner) {}

    /// @notice Check if a repoId has already been claimed.
    function isClaimed(bytes32 repoId) external view returns (bool) {
        return repoOwner[repoId] != address(0);
    }

    /// @notice Direct claim: wallet calls it themselves (no relayer).
    function claimRepo(bytes32 repoId, string calldata meta) external {
        require(repoOwner[repoId] == address(0), "already claimed");
        repoOwner[repoId] = msg.sender;
        emit RepoClaimed(repoId, msg.sender, meta);
    }

    /// @notice Relayer claim: backend pays gas, but sets the true owner wallet.
    /// Only the contract owner (your relayer) can call this.
    function claimRepoFor(bytes32 repoId, string calldata meta, address owner_) external onlyOwner {
        require(owner_ != address(0), "zero owner");
        require(repoOwner[repoId] == address(0), "already claimed");
        repoOwner[repoId] = owner_;
        reposByOwner[owner_].push(repoId);
        emit RepoClaimed(repoId, owner_, meta);
    }

    function getReposByOwner(address owner_) external view returns (bytes32[] memory) {
        return reposByOwner[owner_];
    }

    /// @notice Allow controlled transfers (optional). Only contract owner (relayer) can transfer.
    function transferRepo(bytes32 repoId, address newOwner) external onlyOwner {
        address old = repoOwner[repoId];
        require(old != address(0), "not claimed");
        require(newOwner != address(0), "zero newOwner");
        repoOwner[repoId] = newOwner;
        emit RepoTransferred(repoId, old, newOwner);
    }
}
