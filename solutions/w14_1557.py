// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract NextcloudBounty {
    address public owner;
    address public developer;
    uint256 public bountyAmount;
    bool public bountyClaimed;
    
    event BountyClaimed(address indexed developer, uint256 amount);
    event BountyUpdated(uint256 newAmount);
    
    constructor() {
        owner = msg.sender;
        bountyAmount = 0;
        bountyClaimed = false;
    }
    
    modifier onlyOwner() {
        require(msg.sender == owner, "Only owner can call this function");
        _;
    }
    
    modifier bountyNotClaimed() {
        require(!bountyClaimed, "Bounty already claimed");
        _;
    }
    
    function setDeveloper(address _developer) external onlyOwner {
        require(_developer != address(0), "Invalid developer address");
        developer = _developer;
    }
    
    function fundBounty() external payable onlyOwner bountyNotClaimed {
        require(msg.value > 0, "Bounty amount must be greater than 0");
        bountyAmount += msg.value;
        emit BountyUpdated(bountyAmount);
    }
    
    function claimBounty() external bountyNotClaimed {
        require(msg.sender == developer, "Only the assigned developer can claim");
        require(bountyAmount > 0, "No bounty available");
        
        bountyClaimed = true;
        uint256 amount = bountyAmount;
        bountyAmount = 0;
        
        payable(developer).transfer(amount);
        emit BountyClaimed(developer, amount);
    }
    
    function withdrawFunds() external onlyOwner {
        require(bountyClaimed || bountyAmount == 0, "Bounty not claimed yet");
        uint256 balance = address(this).balance;
        if (balance > 0) {
            payable(owner).transfer(balance);
        }
    }
    
    // Fallback function to receive TRC-20 tokens (for TRON network)
    receive() external payable {
        if (!bountyClaimed && msg.sender == owner) {
            bountyAmount += msg.value;
            emit BountyUpdated(bountyAmount);
        }
    }
}
