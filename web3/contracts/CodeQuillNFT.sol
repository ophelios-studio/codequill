// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

import "@openzeppelin/contracts/token/ERC721/ERC721.sol";
import "@openzeppelin/contracts/token/ERC721/extensions/ERC721URIStorage.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract CodeQuillNFT is ERC721URIStorage, Ownable {

    uint256 private _tokenIdCounter;

    constructor(address initialOwner)
    ERC721("Code Quill NFT", "CODEQUILL")
    Ownable(initialOwner)
    {}

    function mintNFT(address recipient,
        string memory tokenURI)
    public onlyOwner
    returns (uint256) {
        uint256 tokenId = _tokenIdCounter++;
        _mint(recipient, tokenId);
        _setTokenURI(tokenId, tokenURI);
        return tokenId;
    }
}

// CONTRACT ADDRESS
// 0x7bc66725f383e8B46D8B8762aDF913A150CfB612