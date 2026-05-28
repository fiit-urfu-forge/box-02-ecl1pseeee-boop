namespace CW3.Utils;

public class ArrayUtils
{
    public static void Sort(int[] arr)
    {
        QuickSort(arr, 0, arr.Length - 1);
    }
    private static void QuickSort(int[] arr, int low, int high)
    {
        if (low >= high)
            return;

        var pivot = SplitArray(arr, low, high);

        QuickSort(arr, low, pivot);
        QuickSort(arr, pivot + 1, high);
    }

    private static int SplitArray(int[] arr, int low, int high)
    {
        var pivot = arr[low + (high - low) / 2];
        var i = low;
        var j = high;

        while (true)
        {
            while (i <= high && arr[i] < pivot)
                i++;

            while (j >= low && arr[j] > pivot)
                j--;

            if (i >= j)
            {
                return j;
            }

            (arr[i], arr[j]) = (arr[j], arr[i]);
            i++;
            j--;
        }
    }

    public static void PrintArray<T>(T[] result)
    {
        for (int i = 0; i < result.Length; i++)
        {
            Console.Write(result[i]);
            if (i < result.Length - 1)
                Console.Write(" ");
        }
        Console.WriteLine();
    }

    public static string ArrayToString<T>(T[] array, string separator = " ")
    {
        if (array == null || array.Length == 0)
            return "0";

        var sb = new System.Text.StringBuilder();

        for (int i = 0; i < array.Length; i++)
        {
            sb.Append(array[i]);
            if (i < array.Length - 1)
                sb.Append(separator);
        }

        return sb.ToString();
    }
}