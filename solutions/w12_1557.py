// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";

contract NextcloudBounty is Ownable, ReentrancyGuard {
    // Bounty structure
    struct Bounty {
        uint256 id;
        string title;
        string description;
        address issuer;
        uint256 rewardAmount;
        address rewardToken;
        bool isActive;
        uint256 createdAt;
        address[] applicants;
        mapping(address => bool) hasApplied;
    }

    // State variables
    uint256 public bountyCount;
    mapping(uint256 => Bounty) public bounties;
    mapping(address => uint256[]) public userBounties;

    // Events
    event BountyCreated(uint256 indexed bountyId, string title, address indexed issuer, uint256 rewardAmount);
    event BountyApplied(uint256 indexed bountyId, address indexed applicant);
    event BountyCompleted(uint256 indexed bountyId, address indexed solver);
    event BountyCancelled(uint256 indexed bountyId);

    // Modifiers
    modifier bountyExists(uint256 _bountyId) {
        require(_bountyId < bountyCount, "Bounty does not exist");
        _;
    }

    modifier bountyActive(uint256 _bountyId) {
        require(bounties[_bountyId].isActive, "Bounty is not active");
        _;
    }

    // Create new bounty
    function createBounty(
        string memory _title,
        string memory _description,
        uint256 _rewardAmount,
        address _rewardToken
    ) external payable returns (uint256) {
        require(bytes(_title).length > 0, "Title cannot be empty");
        require(_rewardAmount > 0, "Reward must be greater than 0");
        require(_rewardToken != address(0), "Invalid token address");

        uint256 bountyId = bountyCount;
        Bounty storage newBounty = bounties[bountyId];
        
        newBounty.id = bountyId;
        newBounty.title = _title;
        newBounty.description = _description;
        newBounty.issuer = msg.sender;
        newBounty.rewardAmount = _rewardAmount;
        newBounty.rewardToken = _rewardToken;
        newBounty.isActive = true;
        newBounty.createdAt = block.timestamp;

        // Transfer reward tokens to contract
        IERC20(_rewardToken).transferFrom(msg.sender, address(this), _rewardAmount);

        bountyCount++;
        userBounties[msg.sender].push(bountyId);

        emit BountyCreated(bountyId, _title, msg.sender, _rewardAmount);
        
        return bountyId;
    }

    // Apply for bounty
    function applyForBounty(uint256 _bountyId) 
        external 
        bountyExists(_bountyId) 
        bountyActive(_bountyId) 
        nonReentrant 
    {
        Bounty storage bounty = bounties[_bountyId];
        require(!bounty.hasApplied[msg.sender], "Already applied");
        require(msg.sender != bounty.issuer, "Issuer cannot apply");

        bounty.hasApplied[msg.sender] = true;
        bounty.applicants.push(msg.sender);

        emit BountyApplied(_bountyId, msg.sender);
    }

    // Complete bounty and release reward
    function completeBounty(uint256 _bountyId, address _solver) 
        external 
        onlyOwner 
        bountyExists(_bountyId) 
        bountyActive(_bountyId) 
        nonReentrant 
    {
        Bounty storage bounty = bounties[_bountyId];
        require(bounty.hasApplied[_solver], "Solver must have applied");

        bounty.isActive = false;
        
        // Transfer reward to solver
        IERC20(bounty.rewardToken).transfer(_solver, bounty.rewardAmount);

        emit BountyCompleted(_bountyId, _solver);
    }

    // Cancel bounty and refund issuer
    function cancelBounty(uint256 _bountyId) 
        external 
        bountyExists(_bountyId) 
        bountyActive(_bountyId) 
        nonReentrant 
    {
        Bounty storage bounty = bounties[_bountyId];
        require(msg.sender == bounty.issuer || msg.sender == owner(), "Not authorized");

        bounty.isActive = false;
        
        // Refund reward to issuer
        IERC20(bounty.rewardToken).transfer(bounty.issuer, bounty.rewardAmount);

        emit BountyCancelled(_bountyId);
    }

    // Get bounty details
    function getBountyDetails(uint256 _bountyId) 
        external 
        view 
        bountyExists(_bountyId) 
        returns (
            uint256 id,
            string memory title,
            string memory description,
            address issuer,
            uint256 rewardAmount,
            address rewardToken,
            bool isActive,
            uint256 createdAt,
            address[] memory applicants
        ) 
    {
        Bounty storage bounty = bounties[_bountyId];
        return (
            bounty.id,
            bounty.title,
            bounty.description,
            bounty.issuer,
            bounty.rewardAmount,
            bounty.rewardToken,
            bounty.isActive,
            bounty.createdAt,
            bounty.applicants
        );
    }

    // Get user's bounties
    function getUserBounties(address _user) external view returns (uint256[] memory) {
        return userBounties[_user];
    }

    // Get bounty applicants count
    function getApplicantsCount(uint256 _bountyId) external view bountyExists(_bountyId) returns (uint256) {
        return bounties[_bountyId].applicants.length;
    }

    // Check if user has applied to bounty
    function hasApplied(uint256 _bountyId, address _user) external view bountyExists(_bountyId) returns (bool) {
        return bounties[_bountyId].hasApplied[_user];
    }

    // Withdraw accidentally sent tokens
    function withdrawToken(address _token, uint256 _amount) external onlyOwner {
        IERC20(_token).transfer(msg.sender, _amount);
    }

    // Receive function to accept native currency
    receive() external payable {}
}
