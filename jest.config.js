module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'jsdom',
  roots: ['<rootDir>/components', '<rootDir>/js', '<rootDir>/tests'],
  
  // Transform files
  transform: {
    '^.+\\.tsx?$': ['ts-jest', {
      tsconfig: {
        jsx: 'react-jsx',
      },
    }],
  },
  
  // Module name mapping
  moduleNameMapper: {
    '^@components/(.*)$': '<rootDir>/components/$1',
    '^@utils/(.*)$': '<rootDir>/js/utils/$1',
    '^@types/(.*)$': '<rootDir>/types/$1',
    '\\.(css|less|scss|sass)$': 'identity-obj-proxy',
    '\\.(jpg|jpeg|png|gif|svg|webp)$': '<rootDir>/tests/__mocks__/fileMock.js',
  },
  
  // Setup files
  setupFilesAfterEnv: ['<rootDir>/tests/setup.ts'],
  
  // Coverage
  collectCoverageFrom: [
    'components/**/*.{ts,tsx}',
    'js/**/*.{ts,tsx}',
    '!**/*.d.ts',
    '!**/*.stories.tsx',
    '!**/node_modules/**',
    '!**/dist/**',
    '!**/build/**',
  ],
  
  coverageThresholds: {
    global: {
      branches: 70,
      functions: 70,
      lines: 70,
      statements: 70,
    },
  },
  
  // Test match patterns
  testMatch: [
    '**/__tests__/**/*.(ts|tsx|js)',
    '**/*.(test|spec).(ts|tsx|js)',
  ],
  
  // Ignore patterns
  testPathIgnorePatterns: [
    '/node_modules/',
    '/dist/',
    '/build/',
  ],
  
  // Globals
  globals: {
    Drupal: {},
    drupalSettings: {},
    jQuery: {},
  },
};