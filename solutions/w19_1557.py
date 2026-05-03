// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract NextcloudIssueFix {
    address public owner;
    uint256 public bountyAmount;
    bool public issueFixed;

    event IssueFixed(address indexed fixer, uint256 bounty);
    event BountyUpdated(uint256 newAmount);

    modifier onlyOwner() {
        require(msg.sender == owner, "Not the owner");
        _;
    }

    constructor() {
        owner = msg.sender;
        bountyAmount = 0;
        issueFixed = false;
    }

    function updateBounty(uint256 _newAmount) external onlyOwner {
        bountyAmount = _newAmount;
        emit BountyUpdated(_newAmount);
    }

    function fixIssue() external {
        require(!issueFixed, "Issue already fixed");
        require(bountyAmount > 0, "No bounty set");

        issueFixed = true;
        payable(msg.sender).transfer(bountyAmount);
        emit IssueFixed(msg.sender, bountyAmount);
    }

    function depositBounty() external payable onlyOwner {
        require(msg.value > 0, "Must send ETH");
        bountyAmount += msg.value;
        emit BountyUpdated(bountyAmount);
    }

    function withdrawRemaining() external onlyOwner {
        require(issueFixed, "Issue not fixed yet");
        uint256 balance = address(this).balance;
        if (balance > 0) {
            payable(owner).transfer(balance);
        }
    }

    receive() external payable {
        bountyAmount += msg.value;
        emit BountyUpdated(bountyAmount);
    }
}
