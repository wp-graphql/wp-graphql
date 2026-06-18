/* eslint-env browser, jest */
import { renderHook, act } from '@testing-library/react';

// Mock the preferences adapter — the hook reads a device pref synchronously
// at mount and writes one on drop. We control both ends.
jest.mock('../../../../src/api/preferences', () => ({
	readDevicePreference: jest.fn(),
	setPreference: jest.fn(),
}));

// eslint-disable-next-line import/first
import {
	readDevicePreference as readDevicePreferenceMock,
	setPreference as setPreferenceMock,
} from '../../../../src/api/preferences';
// eslint-disable-next-line import/first
import { useResponseTabOrder } from '../../../../src/hooks/useResponseTabOrder';

const TABS = [
	{ name: 'ext:errors', title: 'Errors' },
	{ name: 'ext:tracing', title: 'Tracing' },
	{ name: 'ext:debug', title: 'Debug' },
	{ name: 'ext:headers', title: 'Headers' },
];

function fakeDataTransfer() {
	return {
		effectAllowed: '',
		dropEffect: '',
		setData: jest.fn(),
		getData: jest.fn(),
	};
}

beforeEach(() => {
	readDevicePreferenceMock.mockReset();
	setPreferenceMock.mockReset();
});

describe('useResponseTabOrder', () => {
	it('returns the input tabs unchanged when no saved order is present', () => {
		readDevicePreferenceMock.mockReturnValue(undefined);
		const { result } = renderHook(() => useResponseTabOrder(TABS));
		expect(result.current.orderedTabs).toEqual(TABS);
	});

	it('reorders to match the saved device preference', () => {
		readDevicePreferenceMock.mockReturnValue([
			'ext:headers',
			'ext:tracing',
			'ext:errors',
			'ext:debug',
		]);
		const { result } = renderHook(() => useResponseTabOrder(TABS));
		expect(result.current.orderedTabs.map((t) => t.name)).toEqual([
			'ext:headers',
			'ext:tracing',
			'ext:errors',
			'ext:debug',
		]);
	});

	it('appends newly-registered tabs not in the saved order at the end', () => {
		readDevicePreferenceMock.mockReturnValue([
			'ext:headers',
			'ext:tracing',
		]);
		const { result } = renderHook(() => useResponseTabOrder(TABS));
		// Saved order covers two; the other two (errors, debug) come from
		// the input list in their original order.
		expect(result.current.orderedTabs.map((t) => t.name)).toEqual([
			'ext:headers',
			'ext:tracing',
			'ext:errors',
			'ext:debug',
		]);
	});

	it('ignores saved-order entries that no longer correspond to a tab', () => {
		readDevicePreferenceMock.mockReturnValue([
			'ext:disappeared',
			'ext:tracing',
		]);
		const { result } = renderHook(() => useResponseTabOrder(TABS));
		expect(result.current.orderedTabs.map((t) => t.name)).toEqual([
			'ext:tracing',
			'ext:errors',
			'ext:debug',
			'ext:headers',
		]);
	});

	it('persists the new order on drop and updates the rendered tabs', () => {
		readDevicePreferenceMock.mockReturnValue(undefined);
		const { result } = renderHook(() => useResponseTabOrder(TABS));

		// Drag `ext:headers` to the LEFT of `ext:errors`.
		act(() => {
			result.current.onDragStart('ext:headers')({
				dataTransfer: fakeDataTransfer(),
			});
		});
		act(() => {
			result.current.onDragOver('ext:errors')({
				preventDefault: jest.fn(),
				dataTransfer: fakeDataTransfer(),
				currentTarget: {
					getBoundingClientRect: () => ({
						left: 0,
						width: 100,
					}),
				},
				clientX: 10, // left half
			});
		});
		act(() => {
			result.current.onDrop('ext:errors')({
				preventDefault: jest.fn(),
			});
		});

		expect(result.current.orderedTabs.map((t) => t.name)).toEqual([
			'ext:headers',
			'ext:errors',
			'ext:tracing',
			'ext:debug',
		]);
		expect(setPreferenceMock).toHaveBeenCalledWith('response_tab_order', [
			'ext:headers',
			'ext:errors',
			'ext:tracing',
			'ext:debug',
		]);
	});

	it('no-ops when dropping a tab onto itself', () => {
		readDevicePreferenceMock.mockReturnValue(undefined);
		const { result } = renderHook(() => useResponseTabOrder(TABS));

		act(() => {
			result.current.onDragStart('ext:tracing')({
				dataTransfer: fakeDataTransfer(),
			});
		});
		act(() => {
			result.current.onDrop('ext:tracing')({
				preventDefault: jest.fn(),
			});
		});

		expect(result.current.orderedTabs.map((t) => t.name)).toEqual(
			TABS.map((t) => t.name)
		);
		expect(setPreferenceMock).not.toHaveBeenCalled();
	});

	it('emits the drag-pos based on cursor x within the target', () => {
		readDevicePreferenceMock.mockReturnValue(undefined);
		const { result } = renderHook(() => useResponseTabOrder(TABS));

		act(() => {
			result.current.onDragOver('ext:tracing')({
				preventDefault: jest.fn(),
				dataTransfer: fakeDataTransfer(),
				currentTarget: {
					getBoundingClientRect: () => ({ left: 0, width: 100 }),
				},
				clientX: 80, // right half
			});
		});
		expect(result.current.dragOverTab).toEqual({
			name: 'ext:tracing',
			pos: 'right',
		});

		act(() => {
			result.current.onDragOver('ext:tracing')({
				preventDefault: jest.fn(),
				dataTransfer: fakeDataTransfer(),
				currentTarget: {
					getBoundingClientRect: () => ({ left: 0, width: 100 }),
				},
				clientX: 20, // left half
			});
		});
		expect(result.current.dragOverTab).toEqual({
			name: 'ext:tracing',
			pos: 'left',
		});
	});
});
