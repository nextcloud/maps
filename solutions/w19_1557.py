// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract BountyPayment is Ownable {
    IERC20 public usdtToken;
    
    // Mapping from issue ID to bounty amount
    mapping(string => uint256) public bounties;
    
    // Mapping from issue ID to solver address
    mapping(string => address) public solvers;
    
    // Events
    event BountyCreated(string indexed issueId, uint256 amount);
    event BountyClaimed(string indexed issueId, address indexed solver, uint256 amount);
    event BountyUpdated(string indexed issueId, uint256 newAmount);
    
    constructor(address _usdtToken) {
        require(_usdtToken != address(0), "Invalid USDT address");
        usdtToken = IERC20(_usdtToken);
    }
    
    // Create a new bounty for an issue
    function createBounty(string memory issueId, uint256 amount) external onlyOwner {
        require(amount > 0, "Amount must be greater than 0");
        require(bounties[issueId] == 0, "Bounty already exists");
        
        require(
            usdtToken.transferFrom(msg.sender, address(this), amount),
            "Transfer failed"
        );
        
        bounties[issueId] = amount;
        emit BountyCreated(issueId, amount);
    }
    
    // Claim bounty for solving an issue
    function claimBounty(string memory issueId) external {
        require(bounties[issueId] > 0, "Bounty does not exist");
        require(solvers[issueId] == address(0), "Bounty already claimed");
        
        uint256 amount = bounties[issueId];
        solvers[issueId] = msg.sender;
        
        // Reset bounty amount to prevent reentrancy
        bounties[issueId] = 0;
        
        require(
            usdtToken.transfer(msg.sender, amount),
            "Transfer failed"
        );
        
        emit BountyClaimed(issueId, msg.sender, amount);
    }
    
    // Update bounty amount
    function updateBounty(string memory issueId, uint256 newAmount) external onlyOwner {
        require(bounties[issueId] > 0, "Bounty does not exist");
        require(solvers[issueId] == address(0), "Bounty already claimed");
        
        uint256 oldAmount = bounties[issueId];
        
        if (newAmount > oldAmount) {
            uint256 difference = newAmount - oldAmount;
            require(
                usdtToken.transferFrom(msg.sender, address(this), difference),
                "Transfer failed"
            );
        } else if (newAmount < oldAmount) {
            uint256 difference = oldAmount - newAmount;
            require(
                usdtToken.transfer(msg.sender, difference),
                "Transfer failed"
            );
        }
        
        bounties[issueId] = newAmount;
        emit BountyUpdated(issueId, newAmount);
    }
    
    // Get bounty details
    function getBounty(string memory issueId) external view returns (uint256 amount, address solver) {
        return (bounties[issueId], solvers[issueId]);
    }
    
    // Withdraw any remaining USDT (emergency function)
    function withdrawRemaining(address to, uint256 amount) external onlyOwner {
        require(to != address(0), "Invalid address");
        require(amount > 0, "Amount must be greater than 0");
        require(
            usdtToken.transfer(to, amount),
            "Transfer failed"
        );
    }
}
