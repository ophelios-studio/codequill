// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/Ownable.sol";

interface ICodeQuillRegistry {
    function repoOwner(bytes32 repoId) external view returns (address);
}

contract CodeQuillSnapshots is Ownable {
    ICodeQuillRegistry public immutable registry;

    struct Snapshot {
        bytes32 commitHash;
        bytes32 totalHash;
        string  ipfsCid;
        uint256 timestamp;
        address author;
        uint256 index;
    }

    mapping(bytes32 => Snapshot) public lastSnapshotOf;
    mapping(bytes32 => Snapshot[]) private snapshotsOf;
    mapping(bytes32 => mapping(bytes32 => uint256)) public snapshotIndexByHash;

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
        // optional duplicate guard vs last snapshot:
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

        // NEW: index lookup
        snapshotIndexByHash[repoId][totalHash] = idx + 1;

        emit SnapshotSubmitted(
            repoId, idx, author, commitHash, totalHash, ipfsCid, block.timestamp
        );
    }

    function getSnapshotsCount(bytes32 repoId) external view returns (uint256) {
        return snapshotsOf[repoId].length;
    }

    function getSnapshotFixed(bytes32 repoId, uint256 index)
    external
    view
    returns (bytes32 commitHash, bytes32 totalHash, uint256 timestamp, address author, uint256 idx)
    {
        Snapshot storage s = snapshotsOf[repoId][index];
        return (s.commitHash, s.totalHash, s.timestamp, s.author, s.index);
    }

    function getSnapshotCid(bytes32 repoId, uint256 index)
    external
    view
    returns (string memory ipfsCid)
    {
        return snapshotsOf[repoId][index].ipfsCid;
    }

    function hasSnapshot(bytes32 repoId, bytes32 totalHash) external view returns (bool) {
        return snapshotIndexByHash[repoId][totalHash] != 0;
    }

    // static-only metadata (no string to keep web3p happy)
    function getSnapshotMetaByHash(bytes32 repoId, bytes32 totalHash)
    external
    view
    returns (uint256 timestamp, address author, uint256 index, bytes32 commitHash)
    {
        uint256 idx1 = snapshotIndexByHash[repoId][totalHash];
        require(idx1 != 0, "not found");
        Snapshot storage s = snapshotsOf[repoId][idx1 - 1];
        return (s.timestamp, s.author, s.index, s.commitHash);
    }

    function getSnapshotCidByHash(bytes32 repoId, bytes32 totalHash)
    external
    view
    returns (string memory ipfsCid)
    {
        uint256 idx1 = snapshotIndexByHash[repoId][totalHash];
        require(idx1 != 0, "not found");
        return snapshotsOf[repoId][idx1 - 1].ipfsCid;
    }
}
