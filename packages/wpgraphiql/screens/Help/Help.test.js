import { cleanup, render, act, waitFor } from "@testing-library/react";
import Help from "./Help";

beforeEach(cleanup);
afterEach(cleanup);

describe("Help Screen", () => {
  test("it should render", () => {
    const { container } = render(<Help />);
    expect(container).toBeTruthy();
  });

  test('absolute links should have target="_blank"', () => {
    const { container } = render(<Help />);
    const links = container.querySelectorAll("a");
    links.forEach((link) => {
      // If it's an absolute url, it should have target _blank
      if (link.href.startsWith("http")) {
        expect(link.getAttribute("target")).toBe("_blank");
      }
    });
  });

  test("each card component should have a title", () => {
    const { container } = render(<Help />);
    const cards = container.querySelectorAll("div.ant-card");
    cards.forEach((card) => {
      // ensure each card has actions
      expect(card.querySelector(".ant-card-head-wrapper")).toBeTruthy();

      // ensure each card has an action link
      expect(
        card.querySelector(".ant-card-head-wrapper .ant-card-head-title")
      ).toBeTruthy();
    });
  });

  test("each card component should have an action", () => {
    const { container } = render(<Help />);
    const cards = container.querySelectorAll("div.ant-card");
    cards.forEach((card) => {
      // ensure each card has actions
      expect(card.querySelector(".ant-card-actions")).toBeTruthy();

      // ensure each card has an action link
      expect(card.querySelector(".ant-card-actions a")).toBeTruthy();
    });
  });
});
