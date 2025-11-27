/**
 * Example test file demonstrating Jest + Testing Library setup
 */

import { screen, fireEvent } from '@testing-library/dom';
import '@testing-library/jest-dom';

describe('DOM Testing Example', () => {
  beforeEach(() => {
    // Clear the document body before each test
    document.body.innerHTML = '';
  });

  test('should create and find an element', () => {
    // Arrange
    document.body.innerHTML = `
      <div>
        <h1>FormFlow Pro</h1>
        <button id="submit-btn">Submit</button>
      </div>
    `;

    // Act & Assert
    expect(screen.getByText('FormFlow Pro')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Submit' })).toBeInTheDocument();
  });

  test('should handle button click events', () => {
    // Arrange
    const handleClick = jest.fn();
    document.body.innerHTML = '<button id="test-btn">Click Me</button>';

    const button = screen.getByRole('button', { name: 'Click Me' });
    button.addEventListener('click', handleClick);

    // Act
    fireEvent.click(button);

    // Assert
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  test('should validate form input', () => {
    // Arrange
    document.body.innerHTML = `
      <form>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required />
        <span id="error" class="error-message" style="display: none;">Invalid email</span>
      </form>
    `;

    const emailInput = screen.getByLabelText('Email');
    const errorSpan = document.getElementById('error');

    // Act - simulate invalid input
    fireEvent.change(emailInput, { target: { value: 'invalid-email' } });

    // Assert - input should have value
    expect(emailInput).toHaveValue('invalid-email');
    expect(emailInput).toBeRequired();
  });

  test('should toggle CSS classes', () => {
    // Arrange
    document.body.innerHTML = '<div id="container" class="collapsed">Content</div>';
    const container = document.getElementById('container');

    // Act
    container.classList.toggle('collapsed');
    container.classList.add('expanded');

    // Assert
    expect(container).not.toHaveClass('collapsed');
    expect(container).toHaveClass('expanded');
  });
});

describe('WordPress Mocks', () => {
  test('should have wp global available', () => {
    expect(global.wp).toBeDefined();
    expect(global.wp.i18n.__).toBeDefined();
  });

  test('should translate text using wp.i18n', () => {
    const translated = global.wp.i18n.__('Hello World');
    expect(translated).toBe('Hello World');
  });

  test('should have jQuery mock available', () => {
    expect(global.jQuery).toBeDefined();
    expect(global.$).toBeDefined();
  });

  test('should have formflow_pro localized data', () => {
    expect(global.formflow_pro).toBeDefined();
    expect(global.formflow_pro.ajax_url).toBe('/wp-admin/admin-ajax.php');
    expect(global.formflow_pro.nonce).toBe('test-nonce');
  });
});
