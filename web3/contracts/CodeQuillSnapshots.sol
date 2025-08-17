// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/Ownable.sol";

interface ICodeQuillRegistry {
    function repoOwner(bytes32 repoId) external view returns (address);
}

contract CodeQuillSnapshots is Ownable {
    ICodeQuillRegistry public immutable registry;

    struct Snapshot {
        bytes32 commitHash;  // optional
        bytes32 totalHash;   // required
        string  ipfsCid;     // optional
        uint256 timestamp;   // block timestamp
        address author;      // repo owner at submission
        uint256 index;       // 0..count-1
    }

    // latest snapshot per repo (handy shortcut)
    mapping(bytes32 => Snapshot) public lastSnapshotOf;
    // full history per repo
    mapping(bytes32 => Snapshot[]) private snapshotsOf;

    event SnapshotSubmitted(
        bytes32 indexed repoId,
        uint256 indexed index,
        address indexed author,
        bytes32 commitHash,
        bytes32 totalHash,
        string  ipfsCid,
        uint256 timestamp
    );

    modifier onlyRepoOwner(bytes32 repoId, address author) {
        require(registry.repoOwner(repoId) == author, "author not repo owner");
        _;
    }

    constructor(address initialOwner, address registryAddress) Ownable(initialOwner) {
        require(registryAddress != address(0), "registry zero");
        registry = ICodeQuillRegistry(registryAddress);
    }

    /// Relayer submit: backend pays gas, sets true author (repo owner)
    function snapRepoFor(
        bytes32 repoId,
        bytes32 commitHash,
        bytes32 totalHash,
        string calldata ipfsCid,
        address author
    )
    external
    onlyOwner
    onlyRepoOwner(repoId, author)
    {
        // optional de-dupe vs last snapshot:
        require(lastSnapshotOf[repoId].totalHash != totalHash, "duplicate totalHash");

        uint256 idx = snapshotsOf[repoId].length;

        Snapshot memory s = Snapshot({
            commitHash: commitHash,
            totalHash:  totalHash,
            ipfsCid:    ipfsCid,
            timestamp:  block.timestamp,
            author:     author,
            index:      idx
        });

        snapshotsOf[repoId].push(s);
        lastSnapshotOf[repoId] = s;

        emit SnapshotSubmitted(
            repoId,
            idx,
            author,
            commitHash,
            totalHash,
            ipfsCid,
            block.timestamp
        );
    }

    /// Latest snapshot (already public via lastSnapshotOf, but kept for symmetry)
    function getLastSnapshot(bytes32 repoId) external view returns (Snapshot memory) {
        return lastSnapshotOf[repoId];
    }

    /// Number of snapshots recorded for a repo
    function getSnapshotsCount(bytes32 repoId) external view returns (uint256) {
        return snapshotsOf[repoId].length;
    }

    /// Snapshot at index (0..count-1). Use this to page through history.
    function getSnapshot(bytes32 repoId, uint256 index) external view returns (Snapshot memory) {
        require(index < snapshotsOf[repoId].length, "index OOB");
        return snapshotsOf[repoId][index];
    }
}
