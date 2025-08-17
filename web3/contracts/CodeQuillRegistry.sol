// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract CodeQuillRegistry {
    // repoId => owner
    mapping(bytes32 => address) public repoOwner;

    event RepoClaimed(bytes32 indexed repoId, address indexed owner, string meta);

    /// claim once; use GitHub numeric id (or canonicalized url) to make repoId
    function claimRepo(bytes32 repoId, string calldata meta) external {
        require(repoOwner[repoId] == address(0), "already claimed");
        repoOwner[repoId] = msg.sender;
        emit RepoClaimed(repoId, msg.sender, meta);
    }

    function isClaimed(bytes32 repoId) external view returns (bool) {
        return repoOwner[repoId] != address(0);
    }
}

// CONTRACT ADDRESS
// 0xbC6C15A2A878300Ac49d51Fc4AA460a4AaF7dc90