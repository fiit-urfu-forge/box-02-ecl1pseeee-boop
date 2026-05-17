namespace CW3.Utils;

/// <summary>
/// Методы расширения для работы со списками (List<int>)
/// </summary>
public static class ListExtensions
{
    /// <summary>
    /// Возвращает элементы из первого списка, которых нет во втором.
    /// Реализует операцию разности множеств (A - B).
    /// </summary>
    public static List<int> CustomExcept(this List<int> list, List<int> except)
    {
        // Если исходный список пустой — возвращаем пустой результат
        if (list == null || list.Count == 0)
        {
            return [];
        }

        var result = new List<int>();

        // Проходим по каждому элементу исходного списка
        foreach (var t in list)
        {
            var found = false;

            // Проверяем, есть ли элемент в списке исключений
            for (var j = 0; j < except.Count; j++)
            {
                if (t != except[j]) continue;
                found = true;
                break;
            }

            // Если элемент не найден в except — добавляем в результат
            if (!found)
            {
                result.Add(t);
            }
        }
        return result;
    }

    /// <summary>
    /// Объединяет два списка в один.
    /// Реализует операцию объединения множеств (A ∪ B).
    /// </summary>
    public static List<int> CustomUnion(this List<int> list, List<int> union)
    {
        // Если первый список пустой — возвращаем второй
        if (list == null || list.Count == 0)
        {
            return union ?? new List<int>();
        }

        // Если второй список пустой — возвращаем первый
        if (union == null || union.Count == 0)
        {
            return list;
        }

        // Создаём результат с заранее известной ёмкостью для оптимизации
        var result = new List<int>(list.Count + union.Count);

        // Добавляем все элементы из первого списка
        foreach (var item in list)
        {
            result.Add(item);
        }

        // Добавляем все элементы из второго списка
        foreach (var item in union)
        {
            result.Add(item);
        }

        return result;
    }

    /// <summary>
    /// Сортирует список по возрастанию.
    /// </summary>
    public static List<int> CustomSort(this List<int> list)
    {
        return CustomCountingSort(list, 1, 20000);
    }

    /// <summary>
    /// Сортировка подсчетом
    /// </summary>

    private static List<int> CustomCountingSort(List<int> list, int startRange, int endRange)
    {
        var numbers = new int[endRange + 1];
        foreach (var listItemValue in list)
        {
            numbers[listItemValue]++;
        }

        var result = new List<int>();
        for (var i = startRange; i < numbers.Length; i++)
        {
            while (numbers[i] > 0)
            {
                result.Add(i);
                numbers[i]--;
            }
        }

        return result;
    }

    /// <summary>
    /// Выводит элементы списка в консоль через разделитель.
    /// </summary>
    public static void PrintArray<T>(List<T> result)
    {
        // Проходим по всем элементам и выводим их
        for (int i = 0; i < result.Count; i++)
        {
            Console.Write(result[i]);

            // Добавляем пробел между элементами, но не после последнего
            if (i < result.Count - 1)
                Console.Write(" ");
        }
        Console.WriteLine();
    }

    /// <summary>
    /// Преобразует список в строку с элементами, разделёнными указанным разделителем.
    /// </summary>
    public static string ListToString<T>(List<T> list, string separator = " ")
    {
        // Пустой список возвращаем как "0"
        if (list == null || list.Count == 0)
            return "0";

        var sb = new System.Text.StringBuilder();

        // Формируем строку через StringBuilder для эффективности
        for (int i = 0; i < list.Count; i++)
        {
            sb.Append(list[i]);

            // Добавляем разделитель между элементами, но не после последнего
            if (i < list.Count - 1)
                sb.Append(separator);
        }
        return sb.ToString();
    }


    public static void RadixSort(uint[] arr)
    {
        int n = arr.Length;

        // Временный массив для сортировки
        uint[] output = new uint[n];

        // Сортируем по каждому байту (4 байта в uint)
        for (int shift = 0; shift < 32; shift += 8)
        {
            // Подсчет количества каждого значения байта (0-255)
            int[] count = new int[256];

            // Подсчитываем частоты
            for (int i = 0; i < n; i++)
            {
                int byteValue = (int)((arr[i] >> shift) & 0xFF);
                count[byteValue]++;
            }

            // Накопительная сумма (позиции)
            for (int i = 1; i < 256; i++)
            {
                count[i] += count[i - 1];
            }

            // Расставляем элементы (с конца для устойчивости)
            for (int i = n - 1; i >= 0; i--)
            {
                int byteValue = (int)((arr[i] >> shift) & 0xFF);
                output[--count[byteValue]] = arr[i];
            }

            // Копируем обратно
            Array.Copy(output, arr, n);
        }
    }
}