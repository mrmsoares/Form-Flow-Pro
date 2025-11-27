/** @type {import('jest').Config} */
module.exports = {
  // Test environment
  testEnvironment: 'jsdom',

  // Setup files
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],

  // Test file patterns
  testMatch: [
    '<rootDir>/tests/js/**/*.test.js',
    '<rootDir>/src/**/*.test.js',
    '<rootDir>/src/**/*.spec.js'
  ],

  // Module file extensions
  moduleFileExtensions: ['js', 'json'],

  // Transform files with babel
  transform: {
    '^.+\\.js$': 'babel-jest'
  },

  // Ignore patterns
  testPathIgnorePatterns: [
    '/node_modules/',
    '/vendor/'
  ],

  // Coverage configuration
  collectCoverageFrom: [
    'src/**/*.js',
    '!src/**/*.min.js',
    '!**/node_modules/**'
  ],

  // Coverage directory
  coverageDirectory: 'coverage/js',

  // Coverage reporters
  coverageReporters: ['text', 'lcov', 'html'],

  // Module name mapper for assets
  moduleNameMapper: {
    '\\.(css|scss|sass)$': '<rootDir>/tests/js/__mocks__/styleMock.js',
    '\\.(jpg|jpeg|png|gif|svg)$': '<rootDir>/tests/js/__mocks__/fileMock.js'
  },

  // Verbose output
  verbose: true
};
